<?php

namespace App\Services;

use App\Core\Database;

class DashboardService
{
    public function __construct(private Database $db)
    {
    }

    public function summary(): array
    {
        $global = $this->db->fetch(
            <<<'SQL'
            SELECT
                COUNT(*) AS total_uploaded_leads,
                COALESCE(SUM(status IN ('completed','failed','no_answer','busy','answered')), 0) AS processed_leads,
                COALESCE(SUM(last_dialed_at IS NOT NULL), 0) AS dialed_leads,
                COALESCE(SUM(status = 'queued'), 0) AS queued_leads,
                COALESCE(SUM(status = 'answered'), 0) AS answered_calls,
                COALESCE(SUM(status = 'failed'), 0) AS failed_calls
            FROM leads
            SQL
        ) ?: [];

        $campaigns = $this->db->fetchAll(
            <<<'SQL'
            SELECT
                c.id,
                c.name,
                c.status,
                COUNT(l.id) AS total_leads,
                COALESCE(SUM(l.status IN ('completed','failed','no_answer','busy','answered')), 0) AS processed_leads,
                COALESCE(SUM(l.last_dialed_at IS NOT NULL), 0) AS dialed_leads,
                COALESCE(SUM(l.status = 'queued'), 0) AS queued_leads,
                COALESCE(SUM(l.status = 'answered'), 0) AS answered_calls,
                COALESCE(SUM(l.status = 'failed'), 0) AS failed_calls
            FROM campaigns c
            LEFT JOIN leads l ON l.campaign_id = c.id
            GROUP BY c.id, c.name, c.status
            ORDER BY c.created_at DESC
            LIMIT 50
            SQL
        );

        $engine = $this->db->fetch(
            "SELECT * FROM engine_heartbeats ORDER BY last_seen_at DESC LIMIT 1"
        );

        return ['global' => $global, 'campaigns' => $campaigns, 'engine' => $engine];
    }

    public function activeCalls(): array
    {
        return $this->db->fetchAll(
            <<<'SQL'
            SELECT
                calls.id,
                campaigns.name AS campaign_name,
                leads.phone_number,
                leads.first_name,
                leads.last_name,
                calls.status,
                calls.dialed_at,
                calls.answered_at,
                calls.channel,
                vendors.name AS vendor_name
            FROM calls
            JOIN campaigns ON campaigns.id = calls.campaign_id
            JOIN leads     ON leads.id     = calls.lead_id
            LEFT JOIN vendors ON vendors.id = calls.vendor_id
            WHERE calls.status IN ('initiated','ringing','answered','playing_prompt','collecting_dtmf')
              AND calls.ended_at IS NULL
            ORDER BY calls.dialed_at DESC
            LIMIT 200
            SQL
        );
    }
}
