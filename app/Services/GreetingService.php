<?php

namespace App\Services;

use App\Models\ApiToken;
use App\Models\Member;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/** Birthday & anniversary wishes: settings, "who is celebrating today", daily send and one-tap wishes. */
class GreetingService
{
    public const KINDS = ['birthday', 'anniversary'];

    private const DEFAULT_TEXT = [
        'birthday' => 'Happy Birthday, {name}! May Lord Mahesh keep you healthy and happy.',
        'anniversary' => 'Happy Anniversary, {name}! May your couple always be happy.',
    ];

    public static function enabled(string $kind, ?string $pradesh = null): bool
    {
        if (Setting::get("greet_{$kind}_on", '1') !== '1') return false;
        return $pradesh === null || ! in_array($pradesh, self::offPradesh(), true);
    }

    /** Pradesh names for which Super Admin switched greetings off. */
    public static function offPradesh(): array
    {
        $v = json_decode((string) Setting::get('greet_off_pradesh', '[]'), true);
        return is_array($v) ? $v : [];
    }

    public static function text(string $kind, string $name): string
    {
        $t = trim((string) Setting::get("greet_{$kind}_text", '')) ?: self::DEFAULT_TEXT[$kind];
        return str_replace('{name}', $name, $t);
    }

    public static function title(string $kind): string
    {
        return $kind === 'birthday' ? 'Happy Birthday' : 'Happy Anniversary';
    }

    /** Members whose birthday / anniversary falls on $day (29 Feb folks are greeted on 28 Feb in non-leap years). Not area-limited here: callers add scope. */
    public static function celebrating(string $kind, ?Carbon $day = null)
    {
        $day ??= now();
        $col = $kind === 'birthday' ? 'members.dob_md' : 'members.anniversary_md';
        $md = [$day->format('md')];
        if ($day->format('md') === '0228' && ! $day->isLeapYear()) $md[] = '0229';
        return Member::query()->whereIn($col, $md)->where('members.status', 'active')->where('members.approval', 'approved');
    }

    /** Daily job. Returns [kind => number greeted]. */
    public function sendDaily(?Carbon $day = null): array
    {
        $day ??= now();
        $out = [];
        foreach (self::KINDS as $kind) {
            $out[$kind] = 0;
            if (Setting::get("greet_{$kind}_on", '1') !== '1') continue;
            $off = self::offPradesh();
            self::celebrating($kind, $day)->join('families', 'families.id', '=', 'members.family_id')
                ->select('members.*', 'families.pradesh as f_pradesh')
                ->chunkById(500, function ($rows) use ($kind, $day, $off, &$out) {
                    foreach ($rows as $m) {
                        if ($m->f_pradesh && in_array($m->f_pradesh, $off, true)) continue;
                        $user = User::where('member_id', $m->id)->where('is_active', true)->first(); // only app users can receive it
                        if (! $user) continue;
                        $fresh = DB::table('greeting_logs')->insertOrIgnore(['kind' => $kind, 'member_id' => $m->id, 'year' => $day->year, 'created_at' => now()]);
                        if (! $fresh) continue;
                        $this->notify($user, self::title($kind), self::text($kind, trim($m->name)), ['type' => $kind]);
                        $out[$kind]++;
                    }
                }, 'members.id', 'id');
        }
        return $out;
    }

    /** Inbox entry (bell) + push to every logged-in device of that user. A failed push never blocks the entry. */
    public function notify(User $user, string $title, string $body, array $data = []): void
    {
        UserNotification::create(['user_id' => $user->id, 'title' => $title . ': ' . $body, 'url' => null]);
        $fcm = app(FcmService::class);
        if (! $fcm->configured()) return;
        ApiToken::where('user_id', $user->id)->whereNotNull('fcm_token')->where('expires_at', '>', now())->pluck('fcm_token')->unique()->take(5)
            ->each(function ($t) use ($fcm, $title, $body, $data) {
                try { $fcm->sendToToken($t, $title, $body, $data); } catch (\Throwable $e) { report($e); }
            });
    }
}
