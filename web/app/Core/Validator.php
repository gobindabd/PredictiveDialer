<?php

namespace App\Core;

class Validator
{
    public static function bangladeshPhone(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value);
        if (str_starts_with($digits, '00880')) {
            $digits = substr($digits, 2);
        }

        if (str_starts_with($digits, '880')) {
            $national = substr($digits, 3);
        } elseif (str_starts_with($digits, '0')) {
            $national = substr($digits, 1);
        } else {
            $national = $digits;
        }

        if (strlen($national) !== 10) {
            return null;
        }

        if (!in_array(substr($national, 0, 2), ['13','14','15','16','17','18','19'], true)) {
            return null;
        }

        return '880' . $national;
    }
}
