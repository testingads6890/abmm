<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function index(Request $r)
    {
        $rows = Event::when($r->kind, fn ($q, $v) => $q->where('kind', $v))->orderByDesc('event_date')->paginate(15)->withQueryString();
        return view('admin.events.index', compact('rows'));
    }

    public function store(Request $r)
    {
        $d = $r->validate(['title' => 'required|max:180', 'description' => 'nullable|max:2000', 'venue' => 'required|max:200', 'event_date' => 'required|date', 'event_time' => 'nullable', 'kind' => 'required|in:event,meeting,parichay', 'series' => 'nullable|in:financial_literacy', 'audience' => 'required|in:country,pradesh,state,district,tehsil', 'audience_value' => 'nullable|max:120', 'is_public' => 'boolean']) + ['created_by' => $r->user()->id];
        if ($d['kind'] === 'parichay') $d += ['parichay' => true, 'profile_required' => true, 'rsvp_enabled' => true, 'capacity' => 500, 'max_guests' => 2];
        Event::create($d);
        return back()->with('ok', 'Event added.');
    }

    public function destroy(Event $event)
    {
        $event->delete();
        return back()->with('ok', 'Event removed.');
    }
}
