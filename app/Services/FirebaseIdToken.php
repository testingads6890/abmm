<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Verifies a Firebase Authentication ID token (RS256 JWT) WITHOUT any SDK: signature against Google's public certs,
 * issuer, audience (= Firebase project id), expiry. Used after the Flutter app completed Firebase Phone (OTP) sign-in.
 */
class FirebaseIdToken
{
    private const CERTS_URL = 'https://www.googleapis.com/robot/v1/metadata/x509/securetoken@system.gserviceaccount.com';

    /** @return array{phone:string,uid:string}|null  phone = last 10 digits */
    public function verifyPhone(string $idToken): ?array
    {
        // Local-only shortcut for development without a Firebase project: "dev:9876543210". Never active outside APP_ENV=local.
        if (app()->environment('local') && config('abmm.firebase.dev_bypass') && str_starts_with($idToken, 'dev:')) {
            $p = preg_replace('/\D/', '', substr($idToken, 4));
            return strlen($p) >= 10 ? ['phone' => substr($p, -10), 'uid' => 'dev-' . substr($p, -10)] : null;
        }
        $project = (string) config('abmm.firebase.project_id');
        $parts = explode('.', $idToken);
        if ($project === '' || count($parts) !== 3) return null;

        $header = json_decode($this->b64($parts[0]), true);
        $claims = json_decode($this->b64($parts[1]), true);
        if (! is_array($header) || ! is_array($claims) || ($header['alg'] ?? '') !== 'RS256' || empty($header['kid'])) return null;

        $cert = $this->certs()[$header['kid']] ?? null;
        if (! $cert || openssl_verify($parts[0] . '.' . $parts[1], $this->b64($parts[2]), $cert, OPENSSL_ALGO_SHA256) !== 1) return null;

        $now = time();
        if (($claims['aud'] ?? null) !== $project || ($claims['iss'] ?? null) !== "https://securetoken.google.com/$project"
            || empty($claims['sub']) || ($claims['exp'] ?? 0) < $now || ($claims['iat'] ?? PHP_INT_MAX) > $now + 60) return null;

        $phone = preg_replace('/\D/', '', (string) ($claims['phone_number'] ?? ''));
        if (strlen($phone) < 7) return null;
        [$dial, $national] = \App\Services\PhoneService::split($phone);   // keeps +91 -> last 10 digits; other countries -> their national number
        return ['phone' => $national, 'dial' => $dial, 'uid' => (string) $claims['sub']];
    }

    private function certs(): array
    {
        return Cache::remember('firebase.certs', 3000, function () {
            $r = Http::timeout(8)->get(self::CERTS_URL);
            return $r->successful() ? (array) $r->json() : [];
        });
    }

    private function b64(string $s): string
    {
        return (string) base64_decode(strtr($s, '-_', '+/') . str_repeat('=', (4 - strlen($s) % 4) % 4));
    }
}
