<?php

namespace App\Services;

use App\Models\Event;
use Illuminate\Support\Facades\{Cache, Http, Log};

/** Creates a Zoom meeting for an online event (Server-to-Server OAuth app). Google Meet links are pasted by the organiser (Meet has no key-less API). */
class ZoomService
{
    public function configured(): bool { return (bool) (config('abmm.zoom.account_id') && config('abmm.zoom.client_id') && config('abmm.zoom.client_secret')); }

    private function token(): ?string
    {
        return Cache::remember('zoom_s2s_token', 3000, function () {
            $r = Http::asForm()->withBasicAuth((string) config('abmm.zoom.client_id'), (string) config('abmm.zoom.client_secret'))
                ->post('https://zoom.us/oauth/token', ['grant_type' => 'account_credentials', 'account_id' => config('abmm.zoom.account_id')]);
            return $r->successful() ? $r->json('access_token') : null;
        });
    }

    /** Fills online_url + online_meeting_id when possible. Returns true on success. */
    public function createFor(Event $e): bool
    {
        if (! $this->configured()) return false;
        try {
            $tok = $this->token(); if (! $tok) return false;
            $start = $e->event_date->copy()->setTimeFromTimeString($e->event_time ?: '10:00:00');
            $r = Http::withToken($tok)->post('https://api.zoom.us/v2/users/me/meetings', ['topic' => $e->title, 'type' => 2, 'start_time' => $start->format('Y-m-d\TH:i:s'), 'timezone' => config('app.timezone', 'Asia/Kolkata'),
                'duration' => 120, 'settings' => ['join_before_host' => false, 'waiting_room' => true, 'mute_upon_entry' => true]]);
            if ($r->failed()) { Log::warning('Zoom create failed: ' . $r->body()); return false; }
            $e->update(['online_provider' => 'zoom', 'online_url' => $r->json('join_url'), 'online_meeting_id' => (string) $r->json('id')]);
            return true;
        } catch (\Throwable $ex) { Log::warning('Zoom error: ' . $ex->getMessage()); return false; }
    }
}
