<?php

namespace App\Services\Phone;

use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

class PhoneNormalizerService
{
    public function normalize(?string $rawPhone, ?string $countryCode = 'US'): ?string
    {
        $phone = trim((string) $rawPhone);
        if ($phone === '') {
            return null;
        }

        try {
            $util = PhoneNumberUtil::getInstance();
            $region = $countryCode ?: 'US';
            $proto = $util->parse($phone, $region);
            if (!$util->isValidNumber($proto)) {
                return null;
            }
            return $util->format($proto, PhoneNumberFormat::E164);
        } catch (\Throwable $e) {
            return null;
        }
    }
}


