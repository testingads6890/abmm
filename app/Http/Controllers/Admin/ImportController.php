<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\LegacyImporter;
use Illuminate\Http\Request;

class ImportController extends Controller
{
    public function index() { return view('admin.import'); }

    public function store(Request $r)
    {
        abort_unless($r->user()->can('import.run'), 403);
        $r->validate(['type' => 'required|in:families,members', 'file' => 'required|file|mimes:csv,txt|max:51200']);
        set_time_limit(300);
        $imp = new LegacyImporter();
        try {
            $dry = $r->boolean('dry_run');
            $stats = $r->type === 'members' ? $imp->importMembers($r->file('file')->getRealPath(), $dry) : $imp->importFamilies($r->file('file')->getRealPath(), $dry);
        } catch (\Throwable $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }
        return back()->with('ok', ($dry ? '[DRY RUN - nothing was saved] ' : '') . "Created {$stats['created']}, Updated {$stats['updated']}, Skipped {$stats['skipped']}")->with('import_errors', $imp->errors);
    }
}
