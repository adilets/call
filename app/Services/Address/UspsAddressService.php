<?php

namespace App\Services\Address;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UspsAddressService
{
    public function validateUsAddress(array $address): array
    {
        return [true, null, null];
        $userId = config('services.usps.user_id');
        $password = config('services.usps.password');

        if (!$userId) {
            return [false, 'USPS USERID is not configured.', null];
        }

        $xml = \sprintf(
            '<AddressValidateRequest USERID="%s"><Address ID="0"><Address1/><Address2>%s</Address2><City>%s</City><State>%s</State><Zip5>%s</Zip5><Zip4/></Address></AddressValidateRequest>',
            htmlspecialchars($userId, ENT_QUOTES),
            htmlspecialchars((string)($address['street'] ?? ''), ENT_QUOTES),
            htmlspecialchars((string)($address['city'] ?? ''), ENT_QUOTES),
            htmlspecialchars((string)($address['state'] ?? ''), ENT_QUOTES),
            htmlspecialchars((string)($address['zip'] ?? ''), ENT_QUOTES),
        );

        try {
            $response = Http::timeout(10)
                ->get('https://secure.shippingapis.com/ShippingAPI.dll', [
                    'API' => 'Verify',
                    'XML' => $xml,
                ]);

            if (!$response->ok()) {
                Log::warning('USPS validation HTTP error', ['status' => $response->status(), 'body' => $response->body()]);
                return [false, 'USPS validation service unavailable.', null];
            }

            $body = $response->body();

            if (str_contains($body, '<Error>')) {
                preg_match('/<Description>(.*?)<\/Description>/', $body, $m);
                $msg = $m[1] ?? 'Invalid address.';
                return [false, $msg, null];
            }

            // Parse normalized fields
            $norm = [
                'street' => self::between($body, '<Address2>', '</Address2>') ?: ($address['street'] ?? null),
                'city'   => self::between($body, '<City>', '</City>') ?: ($address['city'] ?? null),
                'state'  => self::between($body, '<State>', '</State>') ?: ($address['state'] ?? null),
                'zip'    => self::between($body, '<Zip5>', '</Zip5>') ?: ($address['zip'] ?? null),
            ];

            return [true, null, $norm];
        } catch (\Throwable $e) {
            Log::error('USPS validation exception', ['error' => $e->getMessage()]);
            return [false, 'USPS validation failed.', null];
        }
    }

    private static function between(string $haystack, string $start, string $end): ?string
    {
        $p = strpos($haystack, $start);
        if ($p === false) return null;
        $p += strlen($start);
        $q = strpos($haystack, $end, $p);
        if ($q === false) return null;
        return trim(substr($haystack, $p, $q - $p));
    }
}


