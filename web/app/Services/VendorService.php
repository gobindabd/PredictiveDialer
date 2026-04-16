<?php

namespace App\Services;

use App\Core\Database;
use RuntimeException;

class VendorService
{
    public function __construct(private Database $db)
    {
    }

    public function create(array $data, array $config = []): int
    {
        $this->validate($data, true);

        return $this->db->transaction(function (Database $db) use ($data): int {
            $id = $db->insert(
                <<<'SQL'
                INSERT INTO vendors (
                    name, trunk_name, sip_username, sip_password, sip_domain,
                    from_user, from_domain, transport, codecs, context,
                    dial_prefix, max_concurrent_calls, cps_limit, priority, is_active
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                SQL,
                $this->vendorParams($data, null)
            );

            $vendor = $db->fetch("SELECT * FROM vendors WHERE id = ?", [$id]);
            $this->syncRealtime($db, $vendor);
            $this->writeRuntimeRegistrationFile($db, $config);

            return $id;
        });
    }

    public function update(int $id, array $data, array $config = []): void
    {
        $vendor = $this->find($id);
        if (!$vendor) {
            throw new RuntimeException('Vendor not found.');
        }

        if (($data['sip_password'] ?? '') === '') {
            $data['sip_password'] = $vendor['sip_password'];
        }

        $this->validate($data, false);

        $this->db->transaction(function (Database $db) use ($id, $data, $config): void {
            $db->execute(
                <<<'SQL'
                UPDATE vendors
                SET name = ?,
                    trunk_name = ?,
                    sip_username = ?,
                    sip_password = ?,
                    sip_domain = ?,
                    from_user = ?,
                    from_domain = ?,
                    transport = ?,
                    codecs = ?,
                    context = ?,
                    dial_prefix = ?,
                    max_concurrent_calls = ?,
                    cps_limit = ?,
                    priority = ?,
                    is_active = ?,
                    updated_at = NOW()
                WHERE id = ?
                SQL,
                [...$this->vendorParams($data, null), $id]
            );

            $vendor = $db->fetch("SELECT * FROM vendors WHERE id = ?", [$id]);
            $this->syncRealtime($db, $vendor);
            $this->writeRuntimeRegistrationFile($db, $config);
        });
    }

    public function find(int $id): ?array
    {
        return $this->db->fetch("SELECT * FROM vendors WHERE id = ?", [$id]);
    }

    public function writeRuntimeRegistrationFile(Database $db, array $config): void
    {
        $path = $config['pjsip_runtime_registration_path'] ?? null;
        if (!$path) {
            return;
        }

        $vendors = $db->fetchAll("SELECT * FROM vendors WHERE is_active = 1 ORDER BY priority ASC, id ASC");
        $content = "; Generated from MariaDB vendors. Do not edit by hand.\n\n";

        foreach ($vendors as $vendor) {
            if (!$vendor['sip_username'] || !$vendor['sip_domain']) {
                continue;
            }

            $trunk = $vendor['trunk_name'];
            $authId = $trunk . '-auth';
            $fileAuthId = $trunk . '-reg-auth';
            $regId = $trunk . '-reg';
            $transport = $vendor['transport'] ?: 'transport-udp';
            $username = $vendor['sip_username'];
            $domain = $vendor['sip_domain'];

            $content .= sprintf("[%s]\n", $fileAuthId);
            $content .= "type=auth\n";
            $content .= "auth_type=userpass\n";
            $content .= sprintf("username=%s\n", $username);
            $content .= sprintf("password=%s\n\n", $vendor['sip_password']);

            $content .= sprintf("[%s]\n", $regId);
            $content .= "type=registration\n";
            $content .= sprintf("outbound_auth=%s\n", $fileAuthId);
            $content .= sprintf("server_uri=sip:%s\n", $domain);
            $content .= sprintf("client_uri=sip:%s@%s\n", $username, $domain);
            $content .= sprintf("contact_user=%s\n", $username);
            $content .= "retry_interval=60\n";
            $content .= "forbidden_retry_interval=600\n";
            $content .= "expiration=3600\n";
            $content .= sprintf("transport=%s\n", $transport);
            $content .= "line=yes\n";
            $content .= sprintf("endpoint=%s\n\n", $trunk);
        }

        if (@file_put_contents($path, $content) === false) {
            return;
        }

        @chmod($path, 0640);
        @shell_exec('asterisk -rx "module reload res_pjsip_outbound_registration.so" 2>&1');
    }

    private function validate(array $data, bool $creating): void
    {
        foreach (['name', 'trunk_name', 'sip_username', 'sip_domain'] as $field) {
            if (trim((string) ($data[$field] ?? '')) === '') {
                throw new RuntimeException(str_replace('_', ' ', $field) . ' is required.');
            }
        }

        if ($creating && trim((string) ($data['sip_password'] ?? '')) === '') {
            throw new RuntimeException('SIP password is required.');
        }

        if (!preg_match('/^[A-Za-z0-9_.-]+$/', (string) $data['trunk_name'])) {
            throw new RuntimeException('Trunk name may contain only letters, numbers, dot, dash, and underscore.');
        }
    }

    private function vendorParams(array $data, ?array $existing): array
    {
        $trunk = trim((string) $data['trunk_name']);
        $domain = trim((string) $data['sip_domain']);
        $username = trim((string) $data['sip_username']);

        return [
            trim((string) $data['name']),
            $trunk,
            $username,
            (string) ($data['sip_password'] ?? ($existing['sip_password'] ?? '')),
            $domain,
            trim((string) ($data['from_user'] ?? $username)),
            trim((string) ($data['from_domain'] ?? $domain)),
            trim((string) ($data['transport'] ?? 'transport-udp')),
            trim((string) ($data['codecs'] ?? 'alaw,ulaw')),
            trim((string) ($data['context'] ?? 'from-carrier')),
            trim((string) ($data['dial_prefix'] ?? '')),
            max(1, (int) ($data['max_concurrent_calls'] ?? 10)),
            max(0.1, (float) ($data['cps_limit'] ?? 1)),
            (int) ($data['priority'] ?? 100),
            isset($data['is_active']) ? 1 : 0,
        ];
    }

    private function syncRealtime(Database $db, ?array $vendor): void
    {
        if (!$vendor) {
            return;
        }

        $trunk = $vendor['trunk_name'];
        $authId = $trunk . '-auth';
        $aorId  = $trunk . '-aor';
        $domain = $vendor['sip_domain'];
        $username = $vendor['sip_username'];
        $password = $vendor['sip_password'];
        $fromUser = $vendor['from_user'] ?: $username;
        $fromDomain = $vendor['from_domain'] ?: $domain;
        $context = $vendor['context'] ?: 'from-' . $trunk;
        $codecs = $vendor['codecs'] ?: 'alaw,ulaw';
        $transport = $vendor['transport'] ?: 'transport-udp';

        if (!(int) $vendor['is_active']) {
            $db->execute("DELETE FROM ps_endpoint_id_ips WHERE id = ?", [$trunk . '-identify']);
            $db->execute("DELETE FROM ps_endpoints WHERE id = ?", [$trunk]);
            $db->execute("DELETE FROM ps_aors WHERE id = ?", [$aorId]);
            $db->execute("DELETE FROM ps_auths WHERE id = ?", [$authId]);
            return;
        }

        $db->execute(
            <<<'SQL'
            REPLACE INTO ps_auths (id, auth_type, username, password)
            VALUES (?, 'userpass', ?, ?)
            SQL,
            [$authId, $username, $password]
        );

        $db->execute(
            <<<'SQL'
            REPLACE INTO ps_aors (id, contact, qualify_frequency)
            VALUES (?, ?, 60)
            SQL,
            [$aorId, 'sip:' . $domain]
        );

        $db->execute(
            <<<'SQL'
            REPLACE INTO ps_endpoints (
                id, transport, aors, context, disallow, allow, outbound_auth,
                from_domain, from_user, rewrite_contact, rtp_symmetric,
                force_rport, direct_media
            ) VALUES (?, ?, ?, ?, 'all', ?, ?, ?, ?, 'yes', 'yes', 'yes', 'no')
            SQL,
            [$trunk, $transport, $aorId, $context, $codecs, $authId, $fromDomain, $fromUser]
        );

        $db->execute(
            <<<'SQL'
            REPLACE INTO ps_endpoint_id_ips (id, endpoint, `match`)
            VALUES (?, ?, ?)
            SQL,
            [$trunk . '-identify', $trunk, $domain]
        );
    }

}
