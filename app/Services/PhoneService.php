<?php

namespace App\Services;

use App\Models\{CountrySetting, Lookup};

/** International phone handling. Convention in this project: `mobile` = national digits, `country_code` = "+91" style dial code. */
class PhoneService
{
    /** @return array<string,string> dial code => country name (longest codes are matched first by split()). */
    public static function dialMap(): array
    {
        $map = [];
        foreach (Lookup::countryCodes() as $country => $dial) $map[self::dial($dial)] = $country;
        try { foreach (CountrySetting::query()->where('is_active', true)->get(['country', 'dial_code']) as $c) $map[self::dial($c->dial_code)] = $c->country; } catch (\Throwable) {}
        $map['+91'] ??= 'INDIA';
        return $map;
    }

    public static function dial(?string $d): string
    {
        $d = preg_replace('/\D/', '', (string) $d);
        return '+' . ($d !== '' ? $d : '91');
    }

    /** "+14155550123" / "14155550123" -> ['+1', '4155550123']. Unknown prefix: falls back to +91 + last 10 digits (legacy behaviour). */
    public static function split(string $raw): array
    {
        $digits = preg_replace('/\D/', '', $raw);
        $map = self::dialMap();
        uksort($map, fn ($a, $b) => strlen($b) <=> strlen($a));
        foreach ($map as $dial => $_) {
            $dd = substr($dial, 1);
            if (str_starts_with($digits, $dd) && strlen($digits) - strlen($dd) >= 6) return [$dial, substr($digits, strlen($dd))];
        }
        return ['+91', substr($digits, -10)];
    }

    public static function valid(string $national, string $dial = '+91'): bool
    {
        return $dial === '+91' ? (bool) preg_match('/^[6-9]\d{9}$/', $national) : (bool) preg_match('/^\d{6,14}$/', $national);
    }

    public static function e164(string $national, string $dial = '+91'): string
    {
        return self::dial($dial) . preg_replace('/\D/', '', $national);
    }
}
