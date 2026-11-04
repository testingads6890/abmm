<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Super Admin: the "Downloads" list shown in the member app (title + "Follow Link" URL). */
class DownloadController extends Controller
{
    public function index() { return view('admin.downloads.index', ['rows' => DB::table('downloads')->orderBy('sort')->orderBy('id')->get()]); }

    public function store(Request $r)
    {
        $d = $r->validate(['title' => 'required|max:250', 'url' => 'required|url|max:500', 'sort' => 'nullable|integer|min:0|max:9999']);
        DB::table('downloads')->insert($d + ['sort' => $d['sort'] ?? 0, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        return back()->with('ok', 'Link added.');
    }

    public function update(Request $r, int $id)
    {
        $d = $r->validate(['title' => 'required|max:250', 'url' => 'required|url|max:500', 'sort' => 'nullable|integer|min:0|max:9999']);
        DB::table('downloads')->where('id', $id)->update($d + ['sort' => $d['sort'] ?? 0, 'is_active' => $r->boolean('is_active'), 'updated_at' => now()]);
        return back()->with('ok', 'Updated.');
    }

    public function destroy(int $id)
    {
        DB::table('downloads')->where('id', $id)->delete();
        return back()->with('ok', 'Link hata diya.');
    }
}
