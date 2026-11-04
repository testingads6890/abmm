<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CountrySetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Language framework: any new language (Gujarati, Marathi, Spanish ...) is just a locale + key=value lines; the app downloads it from GET translations/{locale}. */
class TranslationController extends Controller
{
    public function index(Request $r)
    {
        $want = $r->filled('new') ? $r->input('new') : $r->input('locale');
        $locale = preg_match('/^[a-z]{2,3}(-[A-Za-z]{2})?$/', (string) $want) ? $want : 'hi';
        $rows = DB::table('translations')->where('locale', $locale)->orderBy('key')->get();
        return view('admin.global.translations', [
            'locale' => $locale, 'text' => $rows->map(fn ($x) => $x->key . ' = ' . $x->value)->implode("\n"),
            'locales' => DB::table('translations')->distinct()->pluck('locale')->merge(CountrySetting::pluck('language'))->push('en', 'hi')->unique()->sort()->values(),
        ]);
    }

    public function save(Request $r)
    {
        $d = $r->validate(['locale' => ['required', 'regex:/^[a-z]{2,3}(-[A-Za-z]{2})?$/'], 'text' => 'nullable|string|max:200000']);
        $n = 0;
        DB::transaction(function () use ($d, &$n) {
            DB::table('translations')->where('locale', $d['locale'])->delete();
            foreach (preg_split('/\R/', (string) $d['text']) as $line) {
                if (! str_contains($line, '=')) continue;
                [$k, $v] = array_map('trim', explode('=', $line, 2));
                if ($k === '' || $v === '') continue;
                DB::table('translations')->insert(['locale' => $d['locale'], 'key' => mb_substr($k, 0, 190), 'value' => $v, 'created_at' => now(), 'updated_at' => now()]); $n++;
            }
        });
        return redirect()->route('admin.translations', ['locale' => $d['locale']])->with('ok', "$n translations saved.");
    }
}
