<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lookup;
use App\Models\Setting;
use App\Services\GreetingService;
use Illuminate\Http\Request;

/** Birthday & anniversary: Super Admin sets texts and per-pradesh on/off; every admin sees today's list for THEIR area (Member area scope). */
class GreetingController extends Controller
{
    public function index()
    {
        $lists = [];
        foreach (GreetingService::KINDS as $kind) {
            $lists[$kind] = GreetingService::celebrating($kind)->with('family:id,family_code,district,pradesh,state')->orderBy('members.name')->limit(300)->get();
        }
        return view('admin.greetings.index', [
            'lists' => $lists,
            'on' => ['birthday' => Setting::get('greet_birthday_on', '1') === '1', 'anniversary' => Setting::get('greet_anniversary_on', '1') === '1'],
            'text' => ['birthday' => GreetingService::text('birthday', '{name}'), 'anniversary' => GreetingService::text('anniversary', '{name}')],
            'pradeshList' => Lookup::where('type', 'pradesh')->orderBy('name')->pluck('name'),
            'off' => GreetingService::offPradesh(),
            'sentToday' => \DB::table('greeting_logs')->whereDate('created_at', today())->selectRaw('kind, count(*) c')->groupBy('kind')->pluck('c', 'kind'),
            'wishesToday' => \DB::table('greeting_wishes')->whereDate('created_at', today())->count(),
        ]);
    }

    public function settings(Request $r)
    {
        $d = $r->validate(['birthday_text' => 'required|max:300', 'anniversary_text' => 'required|max:300', 'off' => 'nullable|array', 'off.*' => 'string|max:200']);
        Setting::put('greet_birthday_on', $r->boolean('birthday_on') ? '1' : '0');
        Setting::put('greet_anniversary_on', $r->boolean('anniversary_on') ? '1' : '0');
        Setting::put('greet_birthday_text', $d['birthday_text']);
        Setting::put('greet_anniversary_text', $d['anniversary_text']);
        Setting::put('greet_off_pradesh', json_encode(array_values($d['off'] ?? [])));
        return back()->with('ok', 'Greeting settings saved.');
    }

    /** Run today's job now (safe to press twice: a member is greeted only once a year). */
    public function send(GreetingService $svc)
    {
        $r = $svc->sendDaily();
        return back()->with('ok', "Bhej diya: Birthday {$r['birthday']}, Anniversary {$r['anniversary']}.");
    }
}
