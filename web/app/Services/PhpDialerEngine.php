<?php

namespace App\Services;

use App\Core\Database;
use Throwable;

class PhpDialerEngine
{
    private bool $running = true;
    private float $lastHeartbeatAt = 0.0;

    public function __construct(
        private Database $db,
        private AmiClient $ami,
        private string $engineId
    ) {
    }

    public function stop(): void
    {
        $this->running = false;
    }

    public function run(): void
    {
        $this->heartbeat('starting');
        $this->ami->connect();

        while ($this->running) {
            try {
                if (!$this->ami->isConnected()) {
                    $this->heartbeat('degraded', ['error' => 'AMI disconnected, reconnecting']);
                    $this->ami->reconnect();
                }

                // ── Drain AMI events FIRST ────────────────────────────────────
                // Process all pending events (Hangup, OriginateResponse, etc.)
                // before running the state machine so that dialCapacity(),
                // releaseLeadsForClosedCalls(), and completeFinishedCampaigns()
                // see up-to-date call states.  Use 0-second timeout — just drain
                // whatever is already in the socket buffer without waiting.
                $this->processAmiEvents(0.0);

                $this->heartbeat('running');
                $this->activateDueCampaigns();
                $this->reconcileStaleCalls();
                $this->releaseLeadsForClosedCalls();
                $this->applyStoppingCampaigns();
                $this->completeFinishedCampaigns();
                $this->dialRunningCampaigns();

                // ── Drain again after dialing ─────────────────────────────────
                // Give Asterisk a short window to respond with OriginateResponse
                // and any other events that arrived during this cycle.
                $this->processAmiEvents(0.2);

            } catch (Throwable $e) {
                $this->heartbeat('degraded', ['error' => $e->getMessage()]);
                // If AMI write or connect failed, ensure socket is marked dead
                // so the next iteration triggers a reconnect.
                if (!$this->ami->isConnected()) {
                    usleep(3_000_000);
                } else {
                    usleep(1_000_000);
                }
            }

            usleep(500_000);
        }

        $this->heartbeat('stopping');
        $this->ami->close();
        $this->heartbeat('stopped');
    }

    private function activateDueCampaigns(): void
    {
        $this->db->execute(
            <<<'SQL'
            UPDATE campaigns
            SET status = 'running',
                started_at = COALESCE(started_at, NOW()),
                updated_at = NOW()
            WHERE status = 'scheduled'
              AND scheduled_at <= NOW()
            SQL
        );
    }

    private function dialRunningCampaigns(): void
    {
        $campaigns = $this->db->fetchAll("SELECT * FROM campaigns WHERE status = 'running'");
        foreach ($campaigns as $campaign) {
            $capacity = $this->dialCapacity($campaign);
            if ($capacity <= 0) {
                continue;
            }

            $leads = $this->leaseLeads((int) $campaign['id'], $capacity);
            foreach ($leads as $lead) {
                $this->originateLead($campaign, $lead);
            }
        }
    }

    private function dialCapacity(array $campaign): int
    {
        $active = $this->db->fetch(
            <<<'SQL'
            SELECT COUNT(*) AS count
            FROM calls
            WHERE campaign_id = ?
              AND status IN ('initiated','ringing','answered','playing_prompt','collecting_dtmf')
            SQL,
            [$campaign['id']]
        );

        return max(0, (int) $campaign['max_concurrent_calls'] - (int) ($active['count'] ?? 0));
    }

    private function leaseLeads(int $campaignId, int $limit): array
    {
        return $this->db->transaction(function (Database $db) use ($campaignId, $limit): array {
            $rows = $db->fetchAll(
                <<<'SQL'
                SELECT id, normalized_phone, phone_number
                FROM leads
                WHERE campaign_id = ?
                  AND status = 'pending'
                  AND (next_attempt_at IS NULL OR next_attempt_at <= NOW())
                ORDER BY id ASC
                LIMIT ?
                FOR UPDATE SKIP LOCKED
                SQL,
                [$campaignId, $limit]
            );

            if (!$rows) {
                return [];
            }

            $ids = array_column($rows, 'id');
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $db->execute(
                "UPDATE leads SET status = 'queued', locked_by = ?, locked_at = NOW(), updated_at = NOW() WHERE id IN ($placeholders)",
                [$this->engineId, ...$ids]
            );

            return $rows;
        });
    }

    private function originateLead(array $campaign, array $lead): void
    {
        $vendor = $this->selectVendor($campaign);
        if (!$vendor) {
            $this->releaseLead((int) $lead['id']);
            return;
        }

        $number = ($vendor['dial_prefix'] ?? '') . $lead['normalized_phone'];
        $actionId = bin2hex(random_bytes(16));
        $promptFile = $this->promptFile($campaign['audio_prompt_id'] ?? null);

        $callId = $this->db->insert(
            <<<'SQL'
            INSERT INTO calls (
                campaign_id, lead_id, vendor_id, destination, status, dialed_at, originate_action_id
            ) VALUES (?, ?, ?, ?, 'initiated', NOW(), ?)
            SQL,
            [$campaign['id'], $lead['id'], $vendor['id'], $number, $actionId]
        );

        try {
            $this->ami->originate(
                $actionId,
                'Local/' . $number . '@predictive-outbound',
                'predictive-call',
                's',
                1,
                [
                    'CAMPAIGN_ID' => (string) $campaign['id'],
                    'LEAD_ID' => (string) $lead['id'],
                    'CALL_ID' => (string) $callId,
                    'PROMPT_FILE' => $promptFile,
                    'TRUNK_NAME' => $vendor['trunk_name'],
                ]
            );

            $this->db->execute(
                <<<'SQL'
                UPDATE leads
                SET status = 'dialing',
                    attempts = attempts + 1,
                    last_dialed_at = NOW(),
                    updated_at = NOW()
                WHERE id = ?
                SQL,
                [$lead['id']]
            );
        } catch (Throwable $e) {
            $this->db->transaction(function (Database $db) use ($callId, $lead, $e): void {
                $db->execute(
                    "UPDATE calls SET status = 'failed', ended_at = NOW(), failure_reason = ?, updated_at = NOW() WHERE id = ?",
                    [substr($e->getMessage(), 0, 255), $callId]
                );
                $db->execute(
                    "UPDATE leads SET status = 'failed', last_disposition = 'originate_failed', locked_by = NULL, locked_at = NULL, completed_at = NOW(), updated_at = NOW() WHERE id = ?",
                    [$lead['id']]
                );
                $db->execute(
                    "INSERT INTO system_logs (level, source, message, context) VALUES ('error', 'dialer-engine', ?, ?)",
                    [
                        'Originate failed for lead ' . $lead['id'] . ': ' . $e->getMessage(),
                        json_encode(['lead_id' => $lead['id'], 'call_id' => $callId, 'error' => $e->getMessage()]),
                    ]
                );
            });
        }
    }

    /**
     * Read all pending AMI events and dispatch them to their handlers.
     *
     * Each event is wrapped in its own try/catch so that a DB error on one
     * event (e.g. a duplicate DTMF insert) does not abort processing of the
     * remaining events in the batch.
     */
    private function processAmiEvents(float $timeout = 0.2): void
    {
        foreach ($this->ami->readEvents($timeout) as $event) {
            try {
                $name = $event['Event'] ?? null;
                if ($name === 'OriginateResponse') {
                    $this->handleOriginateResponse($event);
                } elseif ($name === 'UserEvent') {
                    $this->handleUserEvent($event);
                } elseif ($name === 'DialEnd') {
                    $this->handleDialEnd($event);
                } elseif ($name === 'Hangup') {
                    $this->handleHangup($event);
                }
            } catch (Throwable $e) {
                // Log the failure but continue processing the rest of the batch.
                try {
                    $this->db->execute(
                        "INSERT INTO system_logs (level, source, message, context) VALUES ('error', 'dialer-engine', ?, ?)",
                        [
                            'AMI event handler error: ' . $e->getMessage(),
                            json_encode(['event' => $event, 'error' => $e->getMessage()]),
                        ]
                    );
                } catch (Throwable) {
                    // If even the log insert fails, swallow silently — the outer
                    // loop will mark the engine degraded on the next cycle.
                }
            }
        }
    }

    private function handleOriginateResponse(array $event): void
    {
        $actionId  = $event['ActionID'] ?? '';
        $uniqueid  = ($event['Uniqueid'] ?? '') !== '' ? $event['Uniqueid'] : null;

        if (($event['Response'] ?? '') === 'Success') {
            // Always save the uniqueid when we have one, even if PredictiveAnswered
            // already advanced the call past 'initiated' (race condition: fast
            // machine-answers can deliver the UserEvent before OriginateResponse).
            // Only update status→ringing when the call is still in 'initiated'.
            $this->db->execute(
                <<<'SQL'
                UPDATE calls
                SET asterisk_uniqueid = COALESCE(asterisk_uniqueid, ?),
                    status            = CASE WHEN status = 'initiated' THEN 'ringing' ELSE status END,
                    updated_at        = NOW()
                WHERE originate_action_id = ?
                  AND status IN ('initiated','ringing','answered','playing_prompt','collecting_dtmf')
                SQL,
                [$uniqueid, $actionId]
            );
            return;
        }

        $this->failByActionId($actionId, 'Originate failed: ' . ($event['Reason'] ?? 'unknown'));
    }

    private function handleUserEvent(array $event): void
    {
        if (($event['UserEvent'] ?? '') === 'PredictiveAnswered') {
            $callId = (int) ($event['CallId'] ?? 0);
            $leadId = (int) ($event['LeadId'] ?? 0);
            $this->db->execute("UPDATE calls SET status = 'answered', answered_at = COALESCE(answered_at, NOW()), updated_at = NOW() WHERE id = ?", [$callId]);
            $this->db->execute("UPDATE leads SET status = 'answered', updated_at = NOW() WHERE id = ?", [$leadId]);
        }

        if (($event['UserEvent'] ?? '') === 'PredictiveDtmf') {
            $digit = (string) ($event['Digit'] ?? '');
            if (preg_match('/^[0-9]$/', $digit)) {
                $this->db->execute(
                    <<<'SQL'
                    INSERT INTO call_dtmf (call_id, lead_id, digit, sequence_no)
                    VALUES (
                        ?, ?, ?,
                        (SELECT COALESCE(MAX(d.sequence_no), 0) + 1
                         FROM call_dtmf d WHERE d.call_id = ?)
                    )
                    SQL,
                    [(int) $event['CallId'], (int) $event['LeadId'], $digit, (int) $event['CallId']]
                );
            }
        }
    }

    private function handleDialEnd(array $event): void
    {
        $status = strtoupper((string) ($event['DialStatus'] ?? ''));
        $mapped = [
            'BUSY' => 'busy',
            'NOANSWER' => 'no_answer',
            'CANCEL' => 'cancelled',
            'CONGESTION' => 'failed',
            'CHANUNAVAIL' => 'failed',
        ][$status] ?? null;

        if ($mapped) {
            $this->db->execute(
                "UPDATE calls SET status = ?, disposition = ?, ended_at = COALESCE(ended_at, NOW()), updated_at = NOW() WHERE asterisk_uniqueid = ? AND status NOT IN ('completed','answered')",
                [$mapped, $status, $event['Uniqueid'] ?? '']
            );
        }
    }

    private function handleHangup(array $event): void
    {
        // When Asterisk originates through a Local/ channel, the OriginateResponse
        // returns the Local channel's Uniqueid (stored as asterisk_uniqueid).
        // The actual SIP leg hangs up with a *different* Uniqueid but the same
        // Linkedid, which equals the Local channel's Uniqueid.
        // Match on EITHER so we close the call regardless of which leg fires first.
        $uniqueid  = (string) ($event['Uniqueid']  ?? '');
        $linkedid  = (string) ($event['Linkedid']  ?? '');
        $cause     = $event['Cause-txt'] ?? ($event['Cause'] ?? null);

        if ($uniqueid === '' && $linkedid === '') {
            return;
        }

        $this->db->execute(
            <<<'SQL'
            UPDATE calls
            SET status = CASE
                    WHEN status IN ('answered','playing_prompt','collecting_dtmf') THEN 'completed'
                    WHEN status = 'ringing' THEN 'no_answer'
                    ELSE status
                END,
                ended_at     = COALESCE(ended_at, NOW()),
                hangup_cause = ?,
                duration_sec = TIMESTAMPDIFF(SECOND, dialed_at, NOW()),
                billsec      = CASE
                    WHEN answered_at IS NULL THEN 0
                    ELSE TIMESTAMPDIFF(SECOND, answered_at, NOW())
                END,
                updated_at   = NOW()
            WHERE ended_at IS NULL
              AND (asterisk_uniqueid = ? OR asterisk_uniqueid = ?)
            SQL,
            [$cause, $uniqueid, $linkedid]
        );
    }

    private function failByActionId(string $actionId, string $reason): void
    {
        $this->db->transaction(function (Database $db) use ($actionId, $reason): void {
            $call = $db->fetch("SELECT id, lead_id FROM calls WHERE originate_action_id = ?", [$actionId]);
            if (!$call) {
                return;
            }

            $db->execute(
                "UPDATE calls SET status = 'failed', failure_reason = ?, ended_at = NOW(), updated_at = NOW() WHERE id = ?",
                [substr($reason, 0, 255), $call['id']]
            );
            $db->execute(
                "UPDATE leads SET status = 'failed', last_disposition = 'originate_failed', completed_at = NOW(), locked_by = NULL, locked_at = NULL, updated_at = NOW() WHERE id = ?",
                [$call['lead_id']]
            );
        });
    }

    private function reconcileStaleCalls(): void
    {
        // ── 1. Unanswered calls stuck in initiated/ringing ────────────────────
        // 'initiated' = Originate sent but no OriginateResponse yet.
        // 'ringing'   = OriginateResponse received, waiting for SIP answer.
        // The Dial() timeout in the dialplan is 30 s; allow 90 s total as a
        // generous buffer before declaring the call failed and freeing capacity.
        // (Previously 3 minutes — that 3-minute window blocked the campaign with
        // max_concurrent_calls=1 for far too long on any silent originate failure.)
        $this->db->execute(
            <<<'SQL'
            UPDATE calls
            SET status       = 'failed',
                ended_at     = NOW(),
                duration_sec = TIMESTAMPDIFF(SECOND, dialed_at, NOW()),
                billsec      = 0,
                failure_reason = 'stale unanswered call reconciled by engine',
                updated_at   = NOW()
            WHERE status IN ('initiated','ringing')
              AND answered_at IS NULL
              AND dialed_at < DATE_SUB(NOW(), INTERVAL 90 SECOND)
            SQL
        );

        // ── 2. Answered calls with NULL asterisk_uniqueid ─────────────────────
        // The Hangup event is matched by asterisk_uniqueid.  If the uniqueid was
        // never stored (engine crashed between originate and OriginateResponse,
        // or Asterisk returned an empty Uniqueid), the Hangup can never close the
        // call — it will block capacity forever.  Clean up after 10 minutes.
        // Cap billsec at 120 s (matches dialplan TIMEOUT(absolute)=120) and derive
        // ended_at from answered_at + billsec — NOT from NOW() — so that a late-
        // firing reconciler never stores an inflated ended_at in the database.
        $this->db->execute(
            <<<'SQL'
            UPDATE calls
            SET billsec      = LEAST(TIMESTAMPDIFF(SECOND, answered_at, NOW()), 120),
                ended_at     = COALESCE(ended_at,
                                   DATE_ADD(answered_at,
                                       INTERVAL LEAST(TIMESTAMPDIFF(SECOND, answered_at, NOW()), 120) SECOND)),
                duration_sec = TIMESTAMPDIFF(SECOND, dialed_at,
                                   COALESCE(ended_at,
                                       DATE_ADD(answered_at,
                                           INTERVAL LEAST(TIMESTAMPDIFF(SECOND, answered_at, NOW()), 120) SECOND))),
                status       = 'completed',
                failure_reason = 'stale call reconciled (no uniqueid — hangup event unmatchable)',
                updated_at   = NOW()
            WHERE status IN ('answered','playing_prompt','collecting_dtmf')
              AND answered_at IS NOT NULL
              AND asterisk_uniqueid IS NULL
              AND answered_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE)
            SQL
        );

        // ── 3. Answered calls with a uniqueid stuck for 2+ hours ──────────────
        // Safety net: Hangup was received but the uniqueid match failed (e.g.
        // AMI socket was dead during the call and was reconnected — old events
        // are lost).  Frees capacity and prevents the live monitor from showing
        // phantom active calls.
        // Same ended_at derivation as clause 2: anchor to answered_at + billsec,
        // never to the reconciler's wall-clock time.
        $this->db->execute(
            <<<'SQL'
            UPDATE calls
            SET billsec      = LEAST(TIMESTAMPDIFF(SECOND, answered_at, NOW()), 120),
                ended_at     = COALESCE(ended_at,
                                   DATE_ADD(answered_at,
                                       INTERVAL LEAST(TIMESTAMPDIFF(SECOND, answered_at, NOW()), 120) SECOND)),
                duration_sec = TIMESTAMPDIFF(SECOND, dialed_at,
                                   COALESCE(ended_at,
                                       DATE_ADD(answered_at,
                                           INTERVAL LEAST(TIMESTAMPDIFF(SECOND, answered_at, NOW()), 120) SECOND))),
                status       = 'completed',
                failure_reason = 'stale answered call reconciled by engine (hangup event missed)',
                updated_at   = NOW()
            WHERE status IN ('answered','playing_prompt','collecting_dtmf')
              AND answered_at IS NOT NULL
              AND answered_at < DATE_SUB(NOW(), INTERVAL 2 HOUR)
            SQL
        );

        // ── 4. Orphaned 'queued'/'dialing' leads with no open call ────────────
        // If the engine crashes between leaseLeads() (which sets status='queued')
        // and originateLead() creating the calls row, or between sending the
        // AMI originate and updating the lead to 'dialing', the lead is stuck.
        // releaseLeadsForClosedCalls() can't recover these because there is no
        // calls row with ended_at IS NOT NULL to JOIN against.
        // Reset after 5 minutes so they are picked up on the next dial cycle.
        $this->db->execute(
            <<<'SQL'
            UPDATE leads
            SET status    = 'pending',
                locked_by = NULL,
                locked_at = NULL,
                updated_at = NOW()
            WHERE status IN ('queued','dialing')
              AND locked_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)
              AND NOT EXISTS (
                  SELECT 1 FROM calls
                  WHERE calls.lead_id = leads.id
                    AND calls.ended_at IS NULL
              )
            SQL
        );
    }

    private function releaseLeadsForClosedCalls(): void
    {
        $this->db->execute(
            <<<'SQL'
            UPDATE leads l
            JOIN calls c ON c.lead_id = l.id
            JOIN campaigns camp ON camp.id = l.campaign_id
            SET l.status = CASE
                    WHEN c.status = 'completed' THEN 'completed'
                    WHEN c.status IN ('no_answer','busy','failed')
                         AND l.attempts < camp.retry_limit THEN 'pending'
                    WHEN c.status = 'busy' THEN 'busy'
                    WHEN c.status = 'no_answer' THEN 'no_answer'
                    ELSE 'failed'
                END,
                l.next_attempt_at = CASE
                    WHEN c.status IN ('no_answer','busy','failed')
                         AND l.attempts < camp.retry_limit
                        THEN DATE_ADD(NOW(), INTERVAL camp.retry_delay_minutes MINUTE)
                    ELSE l.next_attempt_at
                END,
                l.last_disposition = c.status,
                l.completed_at = CASE
                    WHEN c.status IN ('no_answer','busy','failed')
                         AND l.attempts < camp.retry_limit THEN l.completed_at
                    ELSE COALESCE(l.completed_at, NOW())
                END,
                l.locked_by = NULL,
                l.locked_at = NULL,
                l.updated_at = NOW()
            WHERE l.status IN ('queued','dialing','answered')
              AND c.ended_at IS NOT NULL
              AND c.id = (
                  SELECT c2.id FROM calls c2
                  WHERE c2.lead_id = l.id
                  ORDER BY c2.dialed_at DESC LIMIT 1
              )
            SQL
        );
    }

    private function applyStoppingCampaigns(): void
    {
        $campaigns = $this->db->fetchAll("SELECT id FROM campaigns WHERE status = 'stopping'");
        foreach ($campaigns as $campaign) {
            $active = $this->db->fetch(
                "SELECT COUNT(*) AS count FROM calls WHERE campaign_id = ? AND status IN ('initiated','ringing','answered','playing_prompt','collecting_dtmf')",
                [$campaign['id']]
            );
            if ((int) ($active['count'] ?? 0) === 0) {
                $this->db->execute("UPDATE campaigns SET status = 'stopped', stopped_at = NOW(), updated_at = NOW() WHERE id = ?", [$campaign['id']]);
            }
        }
    }

    private function completeFinishedCampaigns(): void
    {
        $campaigns = $this->db->fetchAll("SELECT id FROM campaigns WHERE status = 'running'");
        foreach ($campaigns as $campaign) {
            $remaining = $this->db->fetch(
                "SELECT COUNT(*) AS count FROM leads WHERE campaign_id = ? AND status IN ('pending','queued','dialing','answered')",
                [$campaign['id']]
            );
            if ((int) ($remaining['count'] ?? 0) === 0) {
                $this->db->execute("UPDATE campaigns SET status = 'completed', completed_at = NOW(), updated_at = NOW() WHERE id = ?", [$campaign['id']]);
            }
        }
    }

    private function selectVendor(array $campaign): ?array
    {
        if (!empty($campaign['vendor_id'])) {
            return $this->db->fetch("SELECT * FROM vendors WHERE id = ? AND is_active = 1", [$campaign['vendor_id']]);
        }
        return $this->db->fetch("SELECT * FROM vendors WHERE is_active = 1 ORDER BY priority ASC, id ASC LIMIT 1");
    }

    private function promptFile(int|string|null $promptId): string
    {
        if (!$promptId) {
            return '';
        }
        $prompt = $this->db->fetch("SELECT asterisk_filename FROM audio_prompts WHERE id = ? AND is_active = 1", [(int) $promptId]);
        return (string) ($prompt['asterisk_filename'] ?? '');
    }

    private function releaseLead(int $leadId): void
    {
        $this->db->execute("UPDATE leads SET status = 'pending', locked_by = NULL, locked_at = NULL, updated_at = NOW() WHERE id = ?", [$leadId]);
    }

    private function heartbeat(string $status, array $metadata = []): void
    {
        $now = microtime(true);
        // Write at most once per second; always write on status transitions
        // (starting / stopping / degraded) so health monitors see them promptly.
        $isTransition = in_array($status, ['starting', 'stopping', 'stopped', 'degraded'], true);
        if (!$isTransition && ($now - $this->lastHeartbeatAt) < 1.0) {
            return;
        }
        $this->lastHeartbeatAt = $now;

        $this->db->execute(
            <<<'SQL'
            INSERT INTO engine_heartbeats (engine_id, hostname, pid, status, last_seen_at, metadata)
            VALUES (?, ?, ?, ?, NOW(), ?)
            ON DUPLICATE KEY UPDATE
                hostname     = VALUES(hostname),
                pid          = VALUES(pid),
                status       = VALUES(status),
                last_seen_at = VALUES(last_seen_at),
                metadata     = VALUES(metadata)
            SQL,
            [$this->engineId, gethostname() ?: 'localhost', (int) (getmypid() ?: 0), $status, json_encode($metadata)]
        );
    }
}
