<?php

namespace App\Services\Messaging;

use App\Models\Setting;
use App\Services\PhoneService;
use Illuminate\Support\Facades\{DB, Http, Mail};

/**
 * One place for WhatsApp (Meta Cloud API), SMS (generic HTTP) and e-mail.
 * - WhatsApp / SMS marketing needs an explicit opt-in (message_optins). Utility messages (receipts, OTP, approvals) do not.
 * - Every attempt is logged in message_logs with an approximate cost, so the Revenue/Messaging screens can show spend.
 */
class MessageService
{
    public function optedIn(string $mobile, string $channel): bool
    {
        $row = DB::table('message_optins')->where(['mobile' => $this->key($mobile), 'channel' => $channel])->first();
        return $row ? (bool) $row->opted_in : false;
    }

    public function setOptIn(string $mobile, string $channel, bool $in, string $source = 'app'): void
    {
        DB::table('message_optins')->updateOrInsert(['mobile' => $this->key($mobile), 'channel' => $channel], ['opted_in' => $in, 'source' => $source, 'updated_at' => now(), 'created_at' => now()]);
    }

    private function key(string $m): string { return preg_replace('/\D/', '', $m); }

    private function log(string $channel, string $category, string $to, ?string $template, ?string $body, string $status, ?string $pid = null, ?string $err = null, ?int $family = null): int
    {
        $cost = $status === 'sent' && $channel === 'whatsapp' ? (float) config("abmm.whatsapp.cost.$category", 0) : 0;
        return DB::table('message_logs')->insertGetId(['channel' => $channel, 'category' => $category, 'to' => $to, 'template' => $template, 'body' => $body ? mb_substr($body, 0, 1000) : null,
            'status' => $status, 'provider_id' => $pid, 'error' => $err ? mb_substr($err, 0, 500) : null, 'cost' => $cost, 'family_id' => $family, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function whatsappConfigured(): bool { return (bool) (config('abmm.whatsapp.phone_number_id') && config('abmm.whatsapp.token')); }

    /** Template message. $params = body variables in order. $documentMediaId = optional PDF header (receipts). */
    public function whatsapp(string $e164, string $template, array $params = [], string $category = 'utility', ?string $documentMediaId = null, ?string $filename = null, ?int $family = null): bool
    {
        if ($category === 'marketing' && ! $this->optedIn($e164, 'whatsapp')) { $this->log('whatsapp', $category, $e164, $template, null, 'skipped', null, 'No opt-in', $family); return false; }
        if (! $this->whatsappConfigured()) { $this->log('whatsapp', $category, $e164, $template, null, 'skipped', null, 'WhatsApp not configured', $family); return false; }
        $components = [];
        if ($documentMediaId) $components[] = ['type' => 'header', 'parameters' => [['type' => 'document', 'document' => ['id' => $documentMediaId, 'filename' => $filename ?: 'receipt.pdf']]]];
        if ($params) $components[] = ['type' => 'body', 'parameters' => array_map(fn ($v) => ['type' => 'text', 'text' => (string) $v], array_values($params))];
        try {
            $res = Http::withToken((string) config('abmm.whatsapp.token'))->acceptJson()->post('https://graph.facebook.com/v20.0/' . config('abmm.whatsapp.phone_number_id') . '/messages', [
                'messaging_product' => 'whatsapp', 'to' => $this->key($e164), 'type' => 'template',
                'template' => ['name' => $template, 'language' => ['code' => config('abmm.whatsapp.template_lang', 'en')], 'components' => $components],
            ]);
            if ($res->failed()) { $this->log('whatsapp', $category, $e164, $template, json_encode($params), 'failed', null, $res->body(), $family); return false; }
            $this->log('whatsapp', $category, $e164, $template, json_encode($params), 'sent', $res->json('messages.0.id'), null, $family);
            return true;
        } catch (\Throwable $e) { $this->log('whatsapp', $category, $e164, $template, null, 'failed', null, $e->getMessage(), $family); return false; }
    }

    /** Uploads a local file to WhatsApp media; returns media id or null. */
    public function whatsappUpload(string $path, string $mime = 'application/pdf'): ?string
    {
        if (! $this->whatsappConfigured() || ! is_file($path)) return null;
        try {
            $res = Http::withToken((string) config('abmm.whatsapp.token'))->attach('file', file_get_contents($path), basename($path), ['Content-Type' => $mime])
                ->post('https://graph.facebook.com/v20.0/' . config('abmm.whatsapp.phone_number_id') . '/media', ['messaging_product' => 'whatsapp', 'type' => $mime]);
            return $res->successful() ? $res->json('id') : null;
        } catch (\Throwable) { return null; }
    }

    public function sms(string $e164, string $text, string $category = 'utility', ?int $family = null): bool
    {
        if ($category === 'marketing' && ! $this->optedIn($e164, 'sms')) { $this->log('sms', $category, $e164, null, $text, 'skipped', null, 'No opt-in', $family); return false; }
        $url = config('abmm.sms.url');
        if (! $url) { $this->log('sms', $category, $e164, null, $text, 'skipped', null, 'SMS not configured', $family); return false; }
        try {
            $res = Http::asForm()->post($url, ['authkey' => config('abmm.sms.key'), 'sender' => config('abmm.sms.sender'), 'to' => $this->key($e164), 'message' => $text]);
            $this->log('sms', $category, $e164, null, $text, $res->successful() ? 'sent' : 'failed', null, $res->successful() ? null : $res->body(), $family);
            return $res->successful();
        } catch (\Throwable $e) { $this->log('sms', $category, $e164, null, $text, 'failed', null, $e->getMessage(), $family); return false; }
    }

    public function email(string $to, string $subject, string $body, ?string $attachPath = null, ?string $attachName = null, ?int $family = null): bool
    {
        try {
            Mail::raw($body, function ($m) use ($to, $subject, $attachPath, $attachName) {
                $m->to($to)->subject($subject);
                if ($attachPath && is_file($attachPath)) $m->attach($attachPath, ['as' => $attachName ?: basename($attachPath), 'mime' => 'application/pdf']);
            });
            $this->log('email', 'utility', $to, null, $subject, 'sent', null, null, $family);
            return true;
        } catch (\Throwable $e) { $this->log('email', 'utility', $to, null, $subject, 'failed', null, $e->getMessage(), $family); return false; }
    }

    /** Handles Meta's webhook: STOP / UNSUBSCRIBE from a user switches WhatsApp marketing off; START switches it on. */
    public function handleWhatsappWebhook(array $payload): int
    {
        $n = 0;
        foreach ($payload['entry'] ?? [] as $e) foreach ($e['changes'] ?? [] as $c) foreach ($c['value']['messages'] ?? [] as $m) {
            $text = strtoupper(trim((string) ($m['text']['body'] ?? '')));
            $from = (string) ($m['from'] ?? '');
            if ($from === '') continue;
            if (in_array($text, ['STOP', 'UNSUBSCRIBE', 'BAND', 'RUKO'], true)) { $this->setOptIn($from, 'whatsapp', false, 'reply'); $n++; }
            elseif (in_array($text, ['START', 'SUBSCRIBE', 'SHURU'], true)) { $this->setOptIn($from, 'whatsapp', true, 'reply'); $n++; }
        }
        return $n;
    }
}
