<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Validator;
use RuntimeException;

class CsvLeadImportService
{
    public function __construct(private Database $db)
    {
    }

    public function validateAndImport(int $campaignId, int $importId, string $filePath): array
    {
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new RuntimeException('Unable to open CSV file.');
        }

        $header = fgetcsv($handle);
        if (!$header) {
            throw new RuntimeException('CSV file is empty.');
        }

        $map = $this->mapHeader($header);
        if (!isset($map['phone_number'])) {
            $this->recordError($importId, 1, 'phone_number', null, 'Phone number column is required.');
            return ['valid' => 0, 'invalid' => 1, 'total' => 0];
        }

        $valid = 0;
        $invalid = 0;
        $total = 0;
        $rowNumber = 1;

        try {
            $this->db->transaction(function (Database $db) use ($handle, $map, $campaignId, $importId, &$valid, &$invalid, &$total, &$rowNumber): void {
                while (($row = fgetcsv($handle)) !== false) {
                    $rowNumber++;
                    $total++;

                    $rawPhone = trim($row[$map['phone_number']] ?? '');
                    $normalized = Validator::bangladeshPhone($rawPhone);
                    if (!$normalized) {
                        $invalid++;
                        $this->recordError($importId, $rowNumber, 'phone_number', $rawPhone, 'Invalid Bangladesh mobile number.');
                        continue;
                    }

                    $firstName = isset($map['first_name']) ? trim($row[$map['first_name']] ?? '') : null;
                    $lastName = isset($map['last_name']) ? trim($row[$map['last_name']] ?? '') : null;

                    try {
                        $db->execute(
                            <<<'SQL'
                            INSERT INTO leads (
                                campaign_id, import_id, phone_number, normalized_phone,
                                first_name, last_name, status
                            ) VALUES (?, ?, ?, ?, ?, ?, 'pending')
                            SQL,
                            [$campaignId, $importId, $rawPhone, $normalized, $firstName, $lastName]
                        );
                        $valid++;
                    } catch (\Throwable) {
                        $invalid++;
                        $this->recordError($importId, $rowNumber, 'phone_number', $rawPhone, 'Duplicate number for this campaign.');
                    }
                }

                $db->execute(
                    <<<'SQL'
                    UPDATE lead_imports
                    SET total_rows = ?, valid_rows = ?, invalid_rows = ?,
                        status = 'completed', completed_at = NOW()
                    WHERE id = ?
                    SQL,
                    [$total, $valid, $invalid, $importId]
                );
            });
        } finally {
            fclose($handle);
        }

        return ['valid' => $valid, 'invalid' => $invalid, 'total' => $total];
    }

    private function mapHeader(array $header): array
    {
        $map = [];
        foreach ($header as $index => $name) {
            $key = strtolower(trim((string) $name));
            if (in_array($key, ['phone','phone_number','number','mobile','msisdn'], true)) {
                $map['phone_number'] = $index;
            } elseif (in_array($key, ['first_name','firstname','first'], true)) {
                $map['first_name'] = $index;
            } elseif (in_array($key, ['last_name','lastname','last'], true)) {
                $map['last_name'] = $index;
            }
        }
        return $map;
    }

    private function recordError(int $importId, int $rowNumber, ?string $field, ?string $rawValue, string $message): void
    {
        $this->db->execute(
            "INSERT INTO lead_import_errors (import_id, row_num, field_name, raw_value, error_message) VALUES (?, ?, ?, ?, ?)",
            [$importId, $rowNumber, $field, $rawValue, $message]
        );
    }
}
