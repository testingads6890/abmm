<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{ActivityLog, Feedback, Setting};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AccountController extends Controller
{
    public function profile(Request $r)
    {
        return view('admin.profile.show', ['u' => $r->user(), 'logs' => ActivityLog::where('user_id', $r->user()->id)->latest('created_at')->limit(30)->get()]);
    }

    public function profileUpdate(Request $r)
    {
        $d = $r->validate(['name' => 'required|max:100', 'mobile' => 'nullable|digits:10|unique:users,mobile,' . $r->user()->id, 'address' => 'nullable|max:300', 'country' => 'nullable|max:60', 'tehsil' => 'nullable|max:80', 'pincode' => 'nullable|digits:6']);
        $r->user()->update($d);
        return back()->with('ok', 'Profile updated.');
    }

    public function desktopNotify(Request $r)
    {
        $r->user()->update(['desktop_notify' => $r->boolean('on')]);
        return response()->noContent();
    }

    public function locale(Request $r, string $lang)
    {
        abort_unless(in_array($lang, ['en', 'hi']), 404);
        session(['locale' => $lang]);
        if ($r->user()) $r->user()->update(['locale' => $lang]);
        return back();
    }

    /* ---- header bell ---- */
    public function readAll(Request $r)
    {
        DB::table('user_notifications')->where('user_id', $r->user()->id)->whereNull('read_at')->update(['read_at' => now()]);
        return back();
    }

    /* ---- Feedback ---- */
    public function feedback(Request $r)
    {
        $rows = Feedback::with('user')->when(! $r->user()->isAdmin(), fn ($q) => $q->where('user_id', $r->user()->id))
            ->when($r->from, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))->when($r->to, fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->latest()->paginate(in_array((int) $r->per, [10, 25, 50, 100]) ? (int) $r->per : 100)->withQueryString();
        return view('admin.feedback.index', compact('rows'));
    }

    public function feedbackStore(Request $r)
    {
        Feedback::create(['user_id' => $r->user()->id, 'message' => $r->validate(['message' => 'required|max:2000'])['message']]);
        return back()->with('ok', 'Feedback sent. Thank you!');
    }

    public function feedbackDestroy(Request $r, Feedback $feedback)
    {
        abort_unless($r->user()->isAdmin() || $feedback->user_id === $r->user()->id, 403);
        $feedback->delete();
        return back()->with('ok', 'Feedback removed.');
    }

    /* ---- Mahesh Vandana ---- */
    public function vandana()
    {
        return view('admin.vandana.index', ['audio' => Setting::get('vandana_audio'), 'image' => Setting::get('vandana_image')]);
    }

    public function vandanaUpload(Request $r)
    {
        abort_unless($r->user()->isAdmin(), 403);
        $r->validate(['audio' => 'nullable|file|mimes:mp3,wav,ogg,m4a|max:20480', 'image' => 'nullable|image|max:4096']);
        foreach (['audio', 'image'] as $k) {
            if ($r->hasFile($k)) {
                if ($old = Setting::get("vandana_$k")) Storage::disk('public')->delete($old);
                Setting::put("vandana_$k", $r->file($k)->store('vandana', 'public'));
            }
        }
        return back()->with('ok', 'Mahesh Vandana updated.');
    }
}
