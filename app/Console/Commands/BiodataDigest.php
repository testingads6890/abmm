<?php

namespace App\Console\Commands;

use App\Models\{MatrimonyProfile, UserNotification};
use App\Services\{AiService, BiodataService};
use App\Services\Messaging\MessageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{DB, Storage};

/** php artisan abmm:biodata-digest [--force] : sends each opted-in member the best new matches as ONE biodata PDF (app notification / e-mail / WhatsApp). Weekly or monthly per member; scheduled daily 09:00. */
class BiodataDigest extends Command
{
    protected $signature = 'abmm:biodata-digest {--force : ignore the weekly / monthly schedule}';
    protected $description = 'Deliver scheduled biodata PDFs of new matches';

    public function handle(AiService $ai, BiodataService $bio, MessageService $msg): int
    {
        $sent = 0;
        foreach (DB::table('biodata_prefs')->where('frequency', '!=', 'off')->get() as $pref) {
            $gap = $pref->frequency === 'monthly' ? 30 : 7;
            if (! $this->option('force') && $pref->last_sent_at && now()->diffInDays($pref->last_sent_at) < $gap) continue;
            $me = MatrimonyProfile::withoutGlobalScopes()->find($pref->profile_id);
            if (! $me || $me->status !== 'paid' || ($me->paid_until && $me->paid_until->lt(today()))) continue;      // paid members only
            $done = json_decode($pref->sent_ids ?: '[]', true);
            $ranked = MatrimonyProfile::withoutGlobalScopes()->where('status', 'paid')->where('visible', true)->where('id', '!=', $me->id)->where('family_id', '!=', $me->family_id)->whereNotIn('id', $done ?: [0])
                ->where('gender', strcasecmp((string) $me->gender, 'male') === 0 ? 'Female' : 'Male')->where(fn ($w) => $w->whereNull('gotra')->orWhere('gotra', '!=', (string) $me->gotra))
                ->where(fn ($w) => $w->whereNull('paid_until')->orWhere('paid_until', '>=', today()))->orderByDesc('registered_at')->limit(200)->get()
                ->map(fn ($p) => ['p' => $p, 'm' => $ai->matchScore($me, $p)])->filter(fn ($x) => $x['m']['score'] >= 50)->sortByDesc(fn ($x) => $x['m']['score'])->take((int) $pref->count)->values();
            DB::table('biodata_prefs')->where('id', $pref->id)->update(['last_sent_at' => now()]);
            if ($ranked->isEmpty()) continue;
            $pdf = $bio->pdfMany($ranked->pluck('p'));
            $file = 'biodata-out/' . $pref->profile_id . '-' . now()->format('Ymd') . '.pdf'; Storage::disk('local')->put($file, $pdf); $abs = Storage::disk('local')->path($file);
            $names = $ranked->map(fn ($x) => $x['p']->name . ' (' . ($x['p']->dob?->age ?? '') . ', ' . $x['p']->city . ')')->implode('; ');
            $channels = json_decode($pref->channels ?: '["app"]', true); $fam = DB::table('families')->find($me->family_id);
            if (in_array('app', $channels, true)) UserNotification::create(['user_id' => $pref->user_id, 'title' => 'For you ' . $ranked->count() . ' new matches: ' . mb_substr($names, 0, 140)]);
            if (in_array('email', $channels, true) && $fam?->email) $msg->email($fam->email, 'New matches for you - ABMM Sampark', "Hello,\n\nBased on your preferences, the biodata of {$ranked->count()} new profiles is attached:\n$names\n\nContact is shared only when both families agree.", $abs, 'naye-rishte.pdf', $fam->id);
            if (in_array('whatsapp', $channels, true) && $fam?->mobile) {
                $e164 = \App\Services\PhoneService::e164($fam->mobile, $fam->country_code ?: '+91'); $media = $msg->whatsappUpload($abs);
                $msg->whatsapp($e164, (string) config('abmm.whatsapp.biodata_template', 'biodata_digest'), [$fam->head_name, (string) $ranked->count()], 'marketing', $media, 'naye-rishte.pdf', $fam->id);
            }
            DB::table('biodata_prefs')->where('id', $pref->id)->update(['sent_ids' => json_encode(array_values(array_unique(array_merge($done, $ranked->pluck('p.id')->all()))))]);
            $sent++;
        }
        $this->info("biodata digests sent: $sent");
        return self::SUCCESS;
    }
}
