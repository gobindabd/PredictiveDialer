<?php

return [
    'app_url' => getenv('APP_URL') ?: 'http://localhost',
    'storage_path' => dirname(__DIR__, 2) . '/storage',
    'csv_upload_path' => dirname(__DIR__, 2) . '/storage/uploads/csv',
    'prompt_original_path' => dirname(__DIR__, 2) . '/storage/prompts/original',
    'prompt_asterisk_path' => dirname(__DIR__, 2) . '/storage/prompts/asterisk',
    'pjsip_runtime_registration_path' => '/etc/asterisk/pjsip_runtime_registrations.conf',
    'max_upload_bytes' => 25 * 1024 * 1024,
];
