<?php

namespace App\Services;

use App\Models\{Business, Family, NotificationLog, User};
use App\Services\Payments\PaymentLedger;
use Illuminate\Support\Facades\DB;

/** Paid advertising: packages (featured / sponsored / banner / category top / push), purchase -> payment -> admin approval -> scheduled serving with impression & click stats. */
class AdService
{
    public function __construct(private PaymentLedger $ledger) {}

    public function purchase(User $u, Business $b, object $pkg, array $d): array
    {
        $f = Family::withoutGlobalScopes()->find($u->family_id);
        $start = \Carbon\Carbon::parse($d['starts_on'] ?? today());
        $target = $pkg->geo_targeting ? GeoTarget::clean($d['target'] ?? []) : [];
        $ad = \App\Models\BusinessAd::create(['business_id' => $b->id, 'title' => $d['title'], 'banner' => $d['banner'] ?? null, 'starts_on' => $start->toDateString(), 'ends_on' => $start->copy()->addDays(max(1, $pkg->days) - 1)->toDateString(),
            'amount' => $pkg->price, 'impression_cap' => $pkg->impression_cap, 'status' => 'awaiting_payment', 'package_id' => $pkg->id, 'kind' => $pkg->kind, 'target' => $target, 'category_id' => $d['category_id'] ?? $b->category_id,
            'link_url' => $d['link_url'] ?? null, 'push_text' => $pkg->kind === 'push' ? mb_substr((string) ($d['push_text'] ?? $d['title']), 0, 200) : null, 'user_id' => $u->id, 'contact_mobile' => $b->mobile]);
        $p = $this->ledger->create(['purpose' => 'ad', 'purpose_ref' => (string) $ad->id, 'amount' => $pkg->price, 'currency' => $pkg->currency, 'country' => $f->country, 'city' => $f->city, 'user_id' => $u->id, 'family_id' => $f->id,
            'payer_name' => $f->head_name, 'payer_email' => $f->email, 'payer_mobile' => PhoneService::e164($f->mobile, $f->country_code ?: '+91'), 'coupon' => $d['coupon'] ?? null, 'audience' => 'advertiser', 'meta' => ['title' => $pkg->name . ': ' . $d['title']]]);
        $ad->update(['payment_id' => $p->id]);
        return [$ad, $p];
    }

    /** Payment confirmed -> waits for the admin's approval. */
    public function paid(int $adId): void { DB::table('business_ads')->where('id', $adId)->where('status', 'awaiting_payment')->update(['status' => 'pending', 'updated_at' => now()]); }

    public function refunded(int $adId): void { DB::table('business_ads')->where('id', $adId)->whereIn('status', ['awaiting_payment', 'pending', 'approved'])->update(['status' => 'cancelled', 'updated_at' => now()]); }

    public function live()
    {
        return DB::table('business_ads as a')->join('businesses as b', 'b.id', '=', 'a.business_id')->where('a.status', 'approved')->where('b.status', 'active')
            ->whereDate('a.starts_on', '<=', today())->whereDate('a.ends_on', '>=', today())
            ->where(fn ($q) => $q->whereNull('a.impression_cap')->orWhereColumn('a.impressions', '<', 'a.impression_cap'));
    }

    /** Ads for one placement, matched to the viewer's geography. Records an impression for each ad returned. */
    public function serve(Family $f, string $kind, ?int $categoryId = null, int $limit = 5): array
    {
        $q = $this->live()->where('a.kind', $kind)->when($categoryId, fn ($w, $v) => $w->where('a.category_id', $v));
        $rows = $q->orderByDesc('a.id')->limit(100)->get(['a.id', 'a.title', 'a.banner', 'a.kind', 'a.link_url', 'a.target', 'a.business_id', 'b.name as business', 'b.mobile', 'a.category_id', 'a.starts_on'])
            ->filter(fn ($a) => GeoTarget::matches(json_decode($a->target ?: '[]', true) ?: [], $f))->shuffle()->take($limit)->values();
        foreach ($rows as $a) $this->count($a->id, 'impressions');
        return $rows->map(fn ($a) => ['id' => $a->id, 'title' => $a->title, 'kind' => $a->kind, 'business_id' => $a->business_id, 'business' => $a->business, 'banner_url' => \App\Support\MemberApp::photoUrl($a->banner), 'link_url' => $a->link_url, 'mobile' => $a->mobile])->all();
    }

    public function count(int $adId, string $col): void
    {
        DB::table('business_ads')->where('id', $adId)->increment($col);
        $upd = DB::table('ad_stats')->where(['ad_id' => $adId, 'day' => today()->toDateString()])->increment($col);
        if (! $upd) DB::table('ad_stats')->insertOrIgnore(['ad_id' => $adId, 'day' => today()->toDateString(), 'impressions' => 0, 'clicks' => 0]) && DB::table('ad_stats')->where(['ad_id' => $adId, 'day' => today()->toDateString()])->increment($col);
    }

    /** Push ads whose start date has come: queue a targeted notification once. @return int sent */
    public function pushDue(): int
    {
        $n = 0;
        foreach (DB::table('business_ads as a')->join('businesses as b', 'b.id', '=', 'a.business_id')->where('a.kind', 'push')->where('a.status', 'approved')->whereNull('a.push_sent_at')
            ->whereDate('a.starts_on', '<=', today())->whereDate('a.ends_on', '>=', today())->get(['a.*', 'b.country as bcountry', 'b.name as bname']) as $a) {
            $t = GeoTarget::clean(json_decode($a->target ?: '[]', true) ?: []);
            $level = 'country'; $val = $t['country'] ?? $a->bcountry;
            foreach (['zone', 'city', 'district', 'state', 'pradesh'] as $k) if (! empty($t[$k])) { $level = $k; $val = $t[$k]; break; }
            if (! $val) continue;
            $col = ['country' => 'country', 'pradesh' => 'pradesh', 'state' => 'state', 'district' => 'district', 'city' => 'city', 'zone' => 'zone'][$level];
            $count = Family::withoutGlobalScopes()->where('status', 'active')->where($col, $val)->count();
            $log = NotificationLog::create(['sent_by' => $a->user_id, 'audience' => $level, 'audience_value' => $val, 'channel' => 'app', 'kind' => 'notice', 'title' => mb_substr($a->bname . ': ' . $a->title, 0, 120),
                'message' => mb_substr((string) ($a->push_text ?: $a->title), 0, 200), 'member_count' => $count, 'topic' => '-', 'delivery_status' => 'queued']);
            Family::withoutGlobalScopes()->where('status', 'active')->where($col, $val)->select('id')->chunkById(1000, fn ($c) => DB::table('notification_recipients')->insertOrIgnore($c->map(fn ($f) => ['notification_id' => $log->id, 'family_id' => $f->id])->all()));
            \App\Jobs\SendTargetedNotification::dispatch($log->id);
            DB::table('business_ads')->where('id', $a->id)->update(['push_sent_at' => now()]); $n++;
        }
        return $n;
    }
}
