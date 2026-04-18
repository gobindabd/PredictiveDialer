<?php

namespace App\Services;

use App\Core\Database;
use InvalidArgumentException;
use RuntimeException;

class CampaignService
{
    private const ALLOWED = ['draft','scheduled','running','paused','stopping','stopped','completed','failed'];

    /**
     * Valid transitions: current status → set of statuses the user may request.
     * The engine transitions (scheduled→running, stopping→stopped, running→completed)
     * are not performed through this method, so they are omitted here.
     */
    private const TRANSITIONS = [
        'draft'     => ['running', 'scheduled'],
        'scheduled' => ['running', 'draft'],
        'running'   => ['paused', 'stopping'],
        'paused'    => ['running', 'stopping'],
        'stopping'  => [],          // engine-owned; no manual change allowed
        'stopped'   => ['running'], // allow restart
        'completed' => ['running'], // allow restart
        'failed'    => ['running'],
    ];

    public function __construct(private Database $db)
    {
    }

    public function create(array $data, int $userId): int
    {
        if (trim((string) ($data['name'] ?? '')) === '') {
            throw new InvalidArgumentException('Campaign name is required.');
        }

        return $this->db->insert(
            <<<'SQL'
            INSERT INTO campaigns (
                name, status, scheduled_at, vendor_id, audio_prompt_id,
                max_concurrent_calls, target_answer_rate, retry_limit,
                retry_delay_minutes, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            SQL,
            [
                trim($data['name']),
                $data['scheduled_at'] ? 'scheduled' : 'draft',
                $data['scheduled_at'] ?: null,
                $data['vendor_id'] ?: null,
                $data['audio_prompt_id'] ?: null,
                max(1, (int) $data['max_concurrent_calls']),
                max(1, min(100, (float) $data['target_answer_rate'])),
                max(0, (int) $data['retry_limit']),
                max(1, (int) $data['retry_delay_minutes']),
                $userId,
            ]
        );
    }

    public function changeStatus(int $campaignId, string $status, ?int $userId): void
    {
        if (!in_array($status, self::ALLOWED, true)) {
            throw new InvalidArgumentException('Invalid campaign status.');
        }

        $this->db->transaction(function (Database $db) use ($campaignId, $status, $userId): void {
            $campaign = $db->fetch("SELECT id, status FROM campaigns WHERE id = ? FOR UPDATE", [$campaignId]);
            if (!$campaign) {
                throw new RuntimeException('Campaign not found.');
            }

            $current = (string) $campaign['status'];
            $allowed = self::TRANSITIONS[$current] ?? [];
            if (!in_array($status, $allowed, true)) {
                throw new RuntimeException(
                    "Cannot change campaign status from '{$current}' to '{$status}'."
                );
            }

            $db->execute(
                <<<'SQL'
                UPDATE campaigns
                SET status = ?,
                    started_at  = IF(? = 'running',  COALESCE(started_at, NOW()),  started_at),
                    paused_at   = IF(? = 'paused',   NOW(), paused_at),
                    stopped_at  = IF(? = 'stopping', NOW(), stopped_at),
                    updated_at  = NOW()
                WHERE id = ?
                SQL,
                [$status, $status, $status, $status, $campaignId]
            );

            $db->execute(
                "INSERT INTO campaign_events (campaign_id, event_type, payload, created_by) VALUES (?, ?, JSON_OBJECT('from', ?, 'to', ?), ?)",
                [$campaignId, 'status_changed', $current, $status, $userId]
            );
        });
    }
}
