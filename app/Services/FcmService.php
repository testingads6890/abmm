<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/** Firebase Cloud Messaging HTTP v1 — no SDK needed (uses curl + openssl). */
class FcmService
{
    public function configured(): bool
    {
        return config('abmm.fcm.project_id') && is_readable(config('abmm.fcm.credentials_path'));
    }

    /** Topic names the mobile app must subscribe to: all, pradesh_<slug>, state_<slug>, district_<slug>, tehsil_<slug> */
    public static function topicFor(string $audience, ?string $value): string
    {
        return $audience === 'country' ? 'all' : $audience . '_' . Str::slug((string) $value, '-');
    }

    /** @return string FCM message name */
    public function sendToTopic(string $topic, string $title, string $body, array $data = []): string
    {
        $res = Http::withToken($this->accessToken())->acceptJson()->post(
            'https://fcm.googleapis.com/v1/projects/' . config('abmm.fcm.project_id') . '/messages:send',
            ['message' => [
                'topic' => $topic,
                'notification' => ['title' => $title, 'body' => $body],
                'data' => array_map('strval', $data),
                'android' => ['priority' => 'high', 'notification' => ['channel_id' => 'abmm_default']],
                'apns' => ['headers' => ['apns-priority' => '10']],
            ]]
        );
        if ($res->failed()) {
            throw new \RuntimeException('FCM error ' . $res->status() . ': ' . Str::limit($res->body(), 300));
        }
        return (string) $res->json('name');
    }

    /** Push to one device (greetings, approvals ...). Returns the FCM message name. */
    public function sendToToken(string $token, string $title, string $body, array $data = []): string
    {
        $res = Http::withToken($this->accessToken())->acceptJson()->post(
            'https://fcm.googleapis.com/v1/projects/' . config('abmm.fcm.project_id') . '/messages:send',
            ['message' => [
                'token' => $token,
                'notification' => ['title' => $title, 'body' => $body],
                'data' => array_map('strval', $data),
                'android' => ['priority' => 'high', 'notification' => ['channel_id' => 'abmm_default']],
                'apns' => ['headers' => ['apns-priority' => '10']],
            ]]
        );
        if ($res->failed()) {
            throw new \RuntimeException('FCM error ' . $res->status() . ': ' . Str::limit($res->body(), 300));
        }
        return (string) $res->json('name');
    }

    private function accessToken(): string
    {
        return Cache::remember('fcm_access_token', 3300, function () {
            $sa = json_decode(file_get_contents(config('abmm.fcm.credentials_path')), true);
            $now = time();
            $b64 = fn ($d) => rtrim(strtr(base64_encode($d), '+/', '-_'), '=');
            $unsigned = $b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])) . '.' . $b64(json_encode([
                'iss' => $sa['client_email'], 'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token', 'iat' => $now, 'exp' => $now + 3600,
            ]));
            openssl_sign($unsigned, $sig, $sa['private_key'], OPENSSL_ALGO_SHA256);
            $res = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $unsigned . '.' . $b64($sig),
            ]);
            if ($res->failed()) throw new \RuntimeException('Google OAuth failed: ' . Str::limit($res->body(), 200));
            return $res->json('access_token');
        });
    }
}
