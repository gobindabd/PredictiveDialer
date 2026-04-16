<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Validator;
use RuntimeException;

class LeadService
{
    private const ACTIVE_CALL_STATES = [
        'initiated',
        'ringing',
        'answered',
        'playing_prompt',
        'collecting_dtmf',
    ];

    public function __construct(private Database $db)
    {
    }

    public function update(int $leadId, array $data): int
    {
        $lead = $this->find($leadId);
        if (!$lead) {
            throw new RuntimeException('Lead not found.');
        }

        if ($this->hasActiveCall($leadId)) {
            throw new RuntimeException('Cannot edit a lead while it has an active call.');
        }

        $normalized = Validator::bangladeshPhone((string) ($data['phone_number'] ?? ''));
        if (!$normalized) {
            throw new RuntimeException('Invalid Bangladesh mobile number.');
        }

        return $this->db->execute(
            <<<'SQL'
            UPDATE leads
            SET phone_number = ?,
                normalized_phone = ?,
                first_name = ?,
                last_name = ?,
                updated_at = NOW()
            WHERE id = ?
            SQL,
            [
                trim((string) $data['phone_number']),
                $normalized,
                trim((string) ($data['first_name'] ?? '')),
                trim((string) ($data['last_name'] ?? '')),
                $leadId,
            ]
        );
    }

    public function resetForReuse(int $leadId): void
    {
        // A manual reset is an explicit override — close all active calls for
        // this lead regardless of age, then reset the lead to pending.
        $this->db->execute(
            <<<'SQL'
            UPDATE calls
            SET status = CASE
                    WHEN answered_at IS NULL THEN 'failed'
                    ELSE 'completed'
                END,
                ended_at = NOW(),
                duration_sec = TIMESTAMPDIFF(SECOND, dialed_at, NOW()),
                billsec = CASE
                    WHEN answered_at IS NULL THEN 0
                    ELSE TIMESTAMPDIFF(SECOND, answered_at, NOW())
                END,
                failure_reason = CASE
                    WHEN answered_at IS NULL THEN 'call aborted by manual lead reset'
                    ELSE failure_reason
                END,
                updated_at = NOW()
            WHERE lead_id = ?
              AND status IN ('initiated','ringing','answered','playing_prompt','collecting_dtmf')
            SQL,
            [$leadId]
        );

        $this->db->execute(
            <<<'SQL'
            UPDATE leads
            SET status = 'pending',
                attempts = 0,
                next_attempt_at = NULL,
                last_dialed_at = NULL,
                completed_at = NULL,
                last_disposition = NULL,
                locked_by = NULL,
                locked_at = NULL,
                updated_at = NOW()
            WHERE id = ?
            SQL,
            [$leadId]
        );
    }

    public function resetCampaignTerminalLeads(int $campaignId): int
    {
        $this->closeStaleCallsForCampaign($campaignId);

        return $this->db->execute(
            <<<'SQL'
            UPDATE leads
            SET status = 'pending',
                attempts = 0,
                next_attempt_at = NULL,
                last_dialed_at = NULL,
                completed_at = NULL,
                last_disposition = NULL,
                locked_by = NULL,
                locked_at = NULL,
                updated_at = NOW()
            WHERE campaign_id = ?
              AND status IN ('answered','completed','failed','no_answer','busy')
              AND NOT EXISTS (
                  SELECT 1
                  FROM calls
                  WHERE calls.lead_id = leads.id
                    AND calls.status IN ('initiated','ringing','answered','playing_prompt','collecting_dtmf')
              )
            SQL,
            [$campaignId]
        );
    }

    public function find(int $leadId): ?array
    {
        return $this->db->fetch(
            "SELECT * FROM leads WHERE id = ?",
            [$leadId]
        );
    }

    private function hasActiveCall(int $leadId): bool
    {
        $placeholders = implode(',', array_fill(0, count(self::ACTIVE_CALL_STATES), '?'));
        $row = $this->db->fetch(
            "SELECT COUNT(*) AS count FROM calls WHERE lead_id = ? AND status IN ($placeholders)",
            [$leadId, ...self::ACTIVE_CALL_STATES]
        );

        return (int) ($row['count'] ?? 0) > 0;
    }

    private function closeStaleCallsForCampaign(int $campaignId): void
    {
        $this->db->execute(
            <<<'SQL'
            UPDATE calls
            SET status = CASE
                    WHEN answered_at IS NULL THEN 'failed'
                    ELSE 'completed'
                END,
                ended_at = NOW(),
                duration_sec = TIMESTAMPDIFF(SECOND, dialed_at, NOW()),
                billsec = CASE
                    WHEN answered_at IS NULL THEN 0
                    ELSE TIMESTAMPDIFF(SECOND, answered_at, NOW())
                END,
                failure_reason = CASE
                    WHEN answered_at IS NULL THEN 'stale call closed during campaign lead reuse'
                    ELSE failure_reason
                END,
                updated_at = NOW()
            WHERE campaign_id = ?
              AND status IN ('initiated','ringing','answered','playing_prompt','collecting_dtmf')
              AND dialed_at < DATE_SUB(NOW(), INTERVAL 3 MINUTE)
            SQL,
            [$campaignId]
        );
    }
}
