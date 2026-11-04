<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Coupon, CountrySetting, Plan, Subscription};
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Plans (monthly / quarterly / yearly) for members, businesses, bureaus, vendors, advertisers, institutions, training + coupons + subscriber list. */
class PlanController extends Controller
{
    public function index(Request $r)
    {
        return view('admin.global.plans', [
            'plans' => Plan::orderBy('audience')->orderBy('sort')->orderBy('price')->get(), 'coupons' => Coupon::orderByDesc('id')->limit(100)->get(),
            'countries' => CountrySetting::where('is_active', true)->pluck('country'),
        ]);
    }

    private function rules(?Plan $p = null): array
    {
        return [
            'code' => ['required', 'alpha_dash', 'max:40', Rule::unique('plans', 'code')->ignore($p?->id)], 'name' => 'required|string|max:120',
            'audience' => ['required', Rule::in(array_keys(Plan::AUDIENCES))], 'period' => ['required', Rule::in(array_keys(Plan::PERIODS))],
            'country' => 'nullable|string|max:100', 'currency' => 'required|alpha|size:3', 'price' => 'required|numeric|min:0|max:99999999',
            'description' => 'nullable|string|max:2000', 'features' => 'nullable|string|max:3000', 'sort' => 'nullable|integer|min:0|max:9999', 'max_items' => 'nullable|integer|min:1|max:1000000',
        ];
    }

    private function fill(Request $r, array $d): array
    {
        $d['currency'] = strtoupper($d['currency']);
        $d['features'] = array_values(array_filter(array_map('trim', preg_split('/\R/', (string) ($d['features'] ?? '')))));
        $d['sort'] = $d['sort'] ?? 0; $d['max_items'] = $d['max_items'] ?? null; $d['is_active'] = $r->boolean('is_active', true);
        return $d;
    }

    public function store(Request $r)
    {
        Plan::create($this->fill($r, $r->validate($this->rules())));
        return back()->with('ok', 'Plan created.');
    }

    public function update(Request $r, Plan $plan)
    {
        $plan->update($this->fill($r, $r->validate($this->rules($plan))));
        return back()->with('ok', 'Plan updated.');
    }

    public function destroy(Plan $plan)
    {
        abort_if(Subscription::where('plan_id', $plan->id)->exists(), 422, 'This plan has subscribers - disable it instead of deleting.');
        $plan->delete();
        return back()->with('ok', 'Plan hata diya.');
    }

    public function couponStore(Request $r)
    {
        $d = $r->validate(['code' => 'required|alpha_dash|max:30|unique:coupons,code', 'type' => 'required|in:percent,flat', 'value' => 'required|numeric|min:0.01|max:99999999',
            'max_uses' => 'nullable|integer|min:1', 'valid_from' => 'nullable|date', 'valid_to' => 'nullable|date|after_or_equal:valid_from', 'audience' => ['nullable', Rule::in(array_keys(Plan::AUDIENCES))]]);
        abort_if($d['type'] === 'percent' && $d['value'] > 100, 422, 'Percent cannot be more than 100.');
        Coupon::create(['code' => strtoupper($d['code'])] + $d + ['is_active' => true]);
        return back()->with('ok', 'Coupon created.');
    }

    public function couponToggle(Coupon $coupon)
    {
        $coupon->update(['is_active' => ! $coupon->is_active]);
        return back()->with('ok', 'Updated.');
    }

    public function subscriptions(Request $r)
    {
        $u = $r->user();
        $ids = $u->areaFamilyIds();
        $rows = Subscription::with(['plan', 'family'])->when($ids, fn ($q) => $q->whereIn('family_id', $ids))
            ->when($r->status, fn ($q, $v) => $q->where('status', $v))->when($r->audience, fn ($q, $v) => $q->where('subscriber_type', $v))
            ->orderByDesc('id')->paginate(40)->withQueryString();
        return view('admin.global.subscriptions', ['rows' => $rows]);
    }
}
