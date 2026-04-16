<?php

namespace App\Services;

use App\Core\Database;
use InvalidArgumentException;

class CampaignService
{
    private const ALLOWED = ['draft','scheduled','running','paused','stopping','stopped','completed','failed'];

    public function __construct(private Database $db)
    {
    }

    public function create(array $data, int $userId): int
    {
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
            $db->execute(
                <<<'SQL'
                UPDATE campaigns
                SET status = ?,
                    started_at = IF(? = 'running', COALESCE(started_at, NOW()), started_at),
                    paused_at = IF(? = 'paused', NOW(), paused_at),
                    stopped_at = IF(? = 'stopped', NOW(), stopped_at),
                    updated_at = NOW()
                WHERE id = ?
                SQL,
                [$status, $status, $status, $status, $campaignId]
            );

            $db->execute(
                "INSERT INTO campaign_events (campaign_id, event_type, payload, created_by) VALUES (?, ?, JSON_OBJECT('status', ?), ?)",
                [$campaignId, 'status_changed', $status, $userId]
            );
        });
    }
}
