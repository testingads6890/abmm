<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{ContactMessage, Donation};
use Illuminate\Http\Request;

class DonationController extends Controller
{
    public function index(Request $r)
    {
        $rows = Donation::when($r->status, fn ($q, $v) => $q->where('status', $v))->latest()->paginate(20)->withQueryString();
        return view('admin.donations.index', ['rows' => $rows, 'total' => Donation::where('status', 'success')->sum('amount')]);
    }

    public function status(Request $r, Donation $donation)
    {
        $donation->update($r->validate(['status' => 'required|in:pending,success,failed', 'payment_ref' => 'nullable|max:60']));
        return back()->with('ok', 'Donation updated.');
    }

    public function messages()
    {
        ContactMessage::where('is_read', false)->update(['is_read' => true]);
        return view('admin.donations.messages', ['rows' => ContactMessage::latest()->paginate(20)]);
    }
}
