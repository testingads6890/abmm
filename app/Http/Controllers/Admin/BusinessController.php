<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Business, BusinessAd, BusinessReview};
use Illuminate\Http\Request;

class BusinessController extends Controller
{
    public function index(Request $r)
    {
        $rows = Business::withCount('reviews')->when($r->status, fn ($q, $v) => $q->where('status', $v))
            ->when($r->q, fn ($q, $t) => $q->where(fn ($w) => $w->where('name', 'like', "%$t%")->orWhere('type', 'like', "%$t%")))
            ->latest()->paginate(20)->withQueryString();
        return view('admin.business.index', compact('rows'));
    }

    public function store(Request $r)
    {
        Business::create($r->validate(['name' => 'required|max:150', 'type' => 'required|max:100', 'address' => 'required|max:500', 'email' => 'nullable|email', 'mobile' => 'required|digits:10', 'description' => 'nullable|max:1000', 'family_id' => 'nullable|exists:families,id', 'contact_person' => 'nullable|max:100', 'member_code' => 'nullable|max:12']) + ['status' => 'active']);
        return back()->with('ok', 'Business added.');
    }

    public function edit(Business $business)
    {
        return view('admin.business.edit', compact('business'));
    }

    public function update(Request $r, Business $business)
    {
        $business->update($r->validate(['name' => 'required|max:150', 'type' => 'required|max:100', 'address' => 'required|max:500', 'email' => 'nullable|email', 'mobile' => 'required|digits:10', 'contact_person' => 'nullable|max:100', 'description' => 'nullable|max:1000', 'status' => 'required|in:pending,active,blocked']));
        return redirect()->route('admin.business.index')->with('ok', 'Business updated.');
    }

    public function status(Request $r, Business $business)
    {
        $business->update($r->validate(['status' => 'required|in:pending,active,blocked']));
        return back()->with('ok', 'Status badla.');
    }

    public function reviews()
    {
        return view('admin.business.reviews', ['rows' => BusinessReview::with('business')->latest()->paginate(20)]);
    }

    public function reviewEdit(BusinessReview $review)
    {
        return view('admin.business.review-edit', ['review' => $review->load('business')]);
    }

    public function reviewUpdate(Request $r, BusinessReview $review)
    {
        $review->update($r->validate([
            'reviewer_name' => 'required|max:120', 'reviewer_mobile' => 'nullable|max:15', 'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|max:2000', 'status' => 'required|in:pending,approved,rejected',
        ]));
        return redirect()->route('admin.reports', 'reviews')->with('ok', 'Review updated.');
    }

    public function reviewStatus(Request $r, BusinessReview $review)
    {
        $review->update($r->validate(['status' => 'required|in:pending,approved,rejected']));
        return back()->with('ok', 'Review updated.');
    }

    public function ads()
    {
        return view('admin.business.ads', ['rows' => BusinessAd::with('business')->latest()->paginate(20), 'businesses' => Business::where('status', 'active')->orderBy('name')->get(['id', 'name'])]);
    }

    public function adStatus(Request $r, BusinessAd $ad)
    {
        $ad->update($r->validate(['status' => 'required|in:pending,approved,rejected']));
        return back()->with('ok', 'Vigyapan status badla.');
    }

    public function adStore(Request $r)
    {
        BusinessAd::create($r->validate(['business_id' => 'required|exists:businesses,id', 'title' => 'required|max:150', 'starts_on' => 'required|date', 'ends_on' => 'required|date|after_or_equal:starts_on', 'amount' => 'required|numeric|min:0', 'payment_ref' => 'nullable|max:60', 'location' => 'nullable|max:150', 'contact_person' => 'nullable|max:100', 'contact_mobile' => 'nullable|digits:10', 'member_code' => 'nullable|max:12']) + ['status' => 'approved']);
        return back()->with('ok', 'Advertisement added.');
    }
}
