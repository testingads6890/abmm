<?php

namespace App\Services;

use App\Models\{User, UserNotification};
use App\Support\Area;
use Illuminate\Support\Facades\DB;

/**
 * Milestone E - investments & business growth: curated opportunities, investor / entrepreneur profiles, pitch decks and showcase,
 * and "interest" leads. Contact details are never shown: an interest notifies the owner, who answers inside the app.
 */
class InvestService
{
    private function tell(?int $uid, string $title, string $body): void
    {
        if (! $uid || ! ($u = User::withoutGlobalScopes()->find($uid))) return;
        try { app(GreetingService::class)->notify($u, $title, $body); } catch (\Throwable $e) { report($e); UserNotification::create(['user_id' => $uid, 'title' => "$title: $body"]); }
    }

    public const DISCLAIMER = 'This is information only, not investment advice. Do your own research and consult a financial advisor before investing. The Mahasabha does not guarantee any returns.';

    public function interest(User $u, array $d): int
    {
        $opp = ! empty($d['opportunity_id']) ? DB::table('investment_opportunities')->where('status', 'live')->find($d['opportunity_id']) : null;
        $pitch = ! empty($d['pitch_id']) ? DB::table('pitches')->where('status', 'approved')->find($d['pitch_id']) : null;
        abort_unless($opp xor $pitch, 422, 'Choose either an opportunity or a pitch.');
        abort_if($pitch && $pitch->user_id === $u->id, 422, 'You cannot show interest in your own pitch.');
        abort_if(DB::table('investment_interests')->where('user_id', $u->id)->where($opp ? 'opportunity_id' : 'pitch_id', $opp->id ?? $pitch->id)->exists(), 422, 'You have already shown interest.');
        $id = DB::table('investment_interests')->insertGetId(['user_id' => $u->id, 'opportunity_id' => $opp->id ?? null, 'pitch_id' => $pitch->id ?? null, 'amount' => $d['amount'] ?? null, 'message' => $d['message'] ?? null, 'status' => 'new', 'created_at' => now(), 'updated_at' => now()]);
        if ($opp) { DB::table('investment_opportunities')->where('id', $opp->id)->increment('interests'); UserNotification::notifyAdmins("Investment interest: {$u->name} -> {$opp->title}", null, \App\Support\Area::of($u)['pradesh'] ?? null); }
        else { DB::table('pitches')->where('id', $pitch->id)->increment('interests'); $this->tell($pitch->user_id, 'Investor interest in your pitch', "{$u->name}: " . ($d['message'] ?? 'Interested')); }
        return $id;
    }

    /** Investors whose sector / ticket preference fits a pitch (for the entrepreneur's "suggested investors"). */
    public function matchInvestors(object $pitch, int $limit = 10): array
    {
        $want = Area::tokens($pitch->sector . ' ' . $pitch->title);
        $out = [];
        foreach (DB::table('investor_profiles')->where(['status' => 'approved', 'visible' => true])->whereIn('role', ['investor', 'both'])->where('user_id', '!=', $pitch->user_id)->get() as $p) {
            $score = count(array_intersect($want, Area::tokens($p->sectors))) * 30;
            if ($pitch->ask_amount && $p->ticket_min !== null && $p->ticket_max !== null && $pitch->ask_amount >= $p->ticket_min && $pitch->ask_amount <= $p->ticket_max) $score += 40;
            if ($pitch->stage && $p->stage_pref && stripos($p->stage_pref, $pitch->stage) !== false) $score += 20;
            if ($p->verified) $score += 10;
            if ($score > 0) $out[] = ['profile' => $p, 'score' => min(100, $score)];
        }
        usort($out, fn ($a, $b) => $b['score'] <=> $a['score']);
        return array_slice($out, 0, $limit);
    }
}
