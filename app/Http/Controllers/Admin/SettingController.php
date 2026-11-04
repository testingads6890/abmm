<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{ActivityLog, Lookup, Office, Setting, User};
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class SettingController extends Controller
{
    public const KEYS = ['org_name', 'sabhapati', 'operational_year', 'contact_mobile', 'contact_email', 'contact_address', 'copyright', 'sms_balance', 'sms_daily_use', 'page_privacy-policy', 'page_terms-and-conditions', 'page_refund-policy', 'theme_primary', 'theme_secondary'];

    public function index()
    {
        return view('admin.settings.index', [
            's' => Setting::pluck('value', 'key'),
            'offices' => Office::orderBy('sort')->orderBy('id')->get(),
            'logs' => ActivityLog::with('user')->latest('created_at')->limit(30)->get(),
        ]);
    }

    public function save(Request $r)
    {
        abort_unless($r->user()->can('settings.manage'), 403);
        $r->validate(['theme_primary' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'], 'theme_secondary' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/']]);
        foreach (self::KEYS as $k) {
            $form = str_replace('-', '_', $k);
            if ($r->has($form)) Setting::put($k, $r->input($form));
        }
        return back()->with('ok', 'Settings saved.');
    }

    public function smtp(Request $r)
    {
        abort_unless($r->user()->can('settings.manage'), 403);
        $d = $r->validate(['smtp_host' => 'nullable|max:150', 'smtp_port' => 'nullable|integer|min:1|max:65535', 'smtp_user' => 'nullable|max:150', 'smtp_pass' => 'nullable|max:200', 'smtp_from' => 'nullable|email', 'smtp_from_name' => 'nullable|max:100', 'smtp_reply' => 'nullable|email', 'smtp_tls' => 'nullable|in:0,1']);
        foreach ($d as $k => $v) {
            if ($k === 'smtp_pass') { if ($v) Setting::put($k, Crypt::encryptString($v)); continue; } // blank = keep old
            Setting::put($k, $v);
        }
        Cache::forget('smtp_cfg');
        return back()->with('ok', 'SMTP settings saved.');
    }

    public function officeStore(Request $r)
    {
        abort_unless($r->user()->can('settings.manage'), 403);
        Office::create($r->validate(['title' => 'required|max:120', 'person' => 'nullable|max:120', 'address' => 'nullable|max:500', 'phones' => 'nullable|max:200', 'email' => 'nullable|email', 'sort' => 'nullable|integer']));
        return back()->with('ok', 'Office added.');
    }

    public function officeDestroy(Request $r, Office $office)
    {
        abort_unless($r->user()->can('settings.manage'), 403);
        $office->delete();
        return back()->with('ok', 'Office removed.');
    }

    public function passwordForm() { return view('admin.account.password'); }

    public function password(Request $r)
    {
        $r->validate(['current_password' => 'required|current_password', 'password' => 'required|min:10|confirmed']);
        $r->validate(['password' => 'different:current_password']);
        $r->user()->update(['password' => $r->password, 'must_change_password' => false]);
        $r->user()->tokens()->delete();
        return redirect()->route('admin.dashboard')->with('ok', 'Password changed.');
    }
}
