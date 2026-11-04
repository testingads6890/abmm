<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use App\Models\{Family, UserNotification};
use App\Support\MemberApp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Member app: Maheshwari bhavans, dharamshalas, hotels, hospitals, clinics, doctors - listing, rooms, availability calendar, booking enquiry, owner tools. */
class PlacesController extends Controller
{
    public const KINDS = ['bhavan', 'dharamshala', 'hotel', 'hospital', 'clinic', 'doctor', 'ambulance', 'blood_bank'];

    private function family(Request $r): Family { return Family::withoutGlobalScopes()->findOrFail($r->user()->family_id); }

    private function visible()
    {
        $q = DB::table('directory_entries')->where('status', 'active');
        if (config('abmm.listing_requires_subscription') && DB::table('plans')->where('audience', 'institution')->where('is_active', true)->exists()) $q->whereDate('listing_until', '>=', today());
        return $q;
    }

    private function shape(object $e): array
    {
        return ['id' => $e->id, 'kind' => $e->kind, 'name' => $e->name, 'parent_id' => $e->parent_id, 'speciality' => $e->speciality, 'country' => $e->country, 'state' => $e->state, 'city' => $e->city, 'zone' => $e->zone, 'address' => $e->address,
            'phone' => $e->phone, 'email' => $e->email, 'website' => $e->website, 'description' => $e->description, 'facilities' => json_decode($e->facilities ?: '[]', true), 'rate_note' => $e->rate_note,
            'member_discount_pct' => (int) $e->member_discount_pct, 'verified' => (bool) $e->verified, 'image_url' => MemberApp::photoUrl($e->image)];
    }

    public function index(Request $r)
    {
        $d = $r->validate(['kind' => 'nullable|in:' . implode(',', self::KINDS), 'country' => 'nullable|string|max:100', 'city' => 'nullable|string|max:150', 'q' => 'nullable|string|max:80', 'speciality' => 'nullable|string|max:80', 'verified' => 'nullable|boolean', 'parent_id' => 'nullable|integer']);
        $q = $this->visible()->when($d['kind'] ?? null, fn ($w, $v) => $w->where('kind', $v))->when($d['country'] ?? null, fn ($w, $v) => $w->where('country', $v))->when($d['city'] ?? null, fn ($w, $v) => $w->where('city', $v))
            ->when($d['q'] ?? null, fn ($w, $v) => $w->where(fn ($x) => $x->where('name', 'like', "%$v%")->orWhere('speciality', 'like', "%$v%")))->when($d['speciality'] ?? null, fn ($w, $v) => $w->where('speciality', 'like', "%$v%"))
            ->when($r->boolean('verified'), fn ($w) => $w->where('verified', true))->when($d['parent_id'] ?? null, fn ($w, $v) => $w->where('parent_id', $v));
        return $q->orderByDesc('verified')->orderBy('name')->limit(100)->get()->map(fn ($e) => $this->shape($e));
    }

    public function show(int $id)
    {
        $e = $this->visible()->find($id) ?? abort(404);
        DB::table('directory_entries')->where('id', $id)->increment('views');
        return $this->shape($e) + ['rooms' => DB::table('directory_rooms')->where(['entry_id' => $id, 'is_active' => true])->get(),
            'doctors' => $e->kind === 'hospital' || $e->kind === 'clinic' ? DB::table('directory_entries')->where(['parent_id' => $id, 'kind' => 'doctor', 'status' => 'active'])->get(['id', 'name', 'speciality', 'phone']) : []];
    }

    /** Busy dates (confirmed bookings) for a room - or the whole property when room_id is omitted. */
    public function calendar(Request $r, int $id)
    {
        $d = $r->validate(['room_id' => 'nullable|integer', 'month' => 'nullable|date_format:Y-m']);
        abort_unless($this->visible()->where('id', $id)->exists(), 404);
        $from = \Carbon\Carbon::parse(($d['month'] ?? now()->format('Y-m')) . '-01'); $to = $from->copy()->endOfMonth();
        $busy = [];
        foreach (DB::table('directory_bookings')->where('entry_id', $id)->where('status', 'confirmed')->when($d['room_id'] ?? null, fn ($w, $v) => $w->where('room_id', $v))
            ->where('from_date', '<=', $to->toDateString())->where('to_date', '>=', $from->toDateString())->get() as $b)
            for ($x = \Carbon\Carbon::parse($b->from_date); $x->lte(\Carbon\Carbon::parse($b->to_date)); $x->addDay()) if ($x->between($from, $to)) $busy[$x->toDateString()] = true;
        ksort($busy);
        return ['month' => $from->format('Y-m'), 'booked' => array_keys($busy)];
    }

    public function enquire(Request $r, int $id)
    {
        $d = $r->validate(['room_id' => 'nullable|integer', 'from_date' => 'required|date|after_or_equal:today', 'to_date' => 'required|date|after_or_equal:from_date', 'guests' => 'required|integer|min:1|max:2000', 'purpose' => 'nullable|string|max:150']);
        $e = $this->visible()->find($id) ?? abort(404);
        abort_unless(in_array($e->kind, ['bhavan', 'dharamshala', 'hotel'], true), 422, 'Booking enquiries are not accepted for this place.');
        if (! empty($d['room_id'])) abort_unless(DB::table('directory_rooms')->where(['id' => $d['room_id'], 'entry_id' => $id, 'is_active' => true])->exists(), 422, 'Invalid room.');
        $clash = DB::table('directory_bookings')->where('entry_id', $id)->where('status', 'confirmed')->when($d['room_id'] ?? null, fn ($w, $v) => $w->where('room_id', $v))
            ->where('from_date', '<=', $d['to_date'])->where('to_date', '>=', $d['from_date'])->exists();
        abort_if($clash, 422, 'A booking is already confirmed for these dates.');
        $bid = DB::table('directory_bookings')->insertGetId($d + ['entry_id' => $id, 'user_id' => $r->user()->id, 'family_id' => $r->user()->family_id, 'status' => 'enquiry', 'created_at' => now(), 'updated_at' => now()]);
        if ($e->owner_user_id) UserNotification::create(['user_id' => $e->owner_user_id, 'title' => "New booking enquiry: {$e->name} ({$d['from_date']} - {$d['to_date']})"]);
        return response()->json(['id' => $bid, 'message' => 'Enquiry sent. The manager will contact you.'], 201);
    }

    public function myBookings(Request $r)
    {
        return DB::table('directory_bookings as b')->join('directory_entries as e', 'e.id', '=', 'b.entry_id')->where('b.user_id', $r->user()->id)->orderByDesc('b.id')->get(['b.id', 'e.name', 'b.from_date', 'b.to_date', 'b.guests', 'b.status', 'b.response']);
    }

    public function cancelBooking(Request $r, int $id)
    {
        $n = DB::table('directory_bookings')->where(['id' => $id, 'user_id' => $r->user()->id])->whereIn('status', ['enquiry', 'confirmed'])->update(['status' => 'cancelled', 'updated_at' => now()]);
        abort_unless($n, 404);
        return ['message' => 'Booking cancelled.'];
    }

    // ---------------- owner / manager ----------------
    public function register(Request $r)
    {
        $d = $r->validate(['kind' => 'required|in:' . implode(',', self::KINDS), 'name' => 'required|string|max:180', 'parent_id' => 'nullable|integer', 'speciality' => 'nullable|string|max:120', 'city' => 'nullable|string|max:150', 'state' => 'nullable|string|max:120',
            'zone' => 'nullable|string|max:100', 'address' => 'required|string|max:400', 'phone' => 'required|string|max:30', 'email' => 'nullable|email|max:150', 'website' => 'nullable|url|max:200', 'description' => 'nullable|string|max:3000',
            'facilities' => 'nullable|array|max:30', 'facilities.*' => 'string|max:60', 'rate_note' => 'nullable|string|max:200', 'member_discount_pct' => 'nullable|integer|min:0|max:100', 'image' => 'nullable|image|max:3072']);
        $f = $this->family($r);
        if ($r->hasFile('image')) $d['image'] = MemberApp::storePhoto($r->file('image'), 'places');
        if (! empty($d['parent_id'])) abort_unless(DB::table('directory_entries')->where(['id' => $d['parent_id'], 'owner_user_id' => $r->user()->id])->exists(), 422, 'Parent is not yours.');
        $id = DB::table('directory_entries')->insertGetId(array_merge($d, ['facilities' => json_encode($d['facilities'] ?? []), 'country' => $f->country, 'city' => $d['city'] ?? $f->city, 'owner_user_id' => $r->user()->id, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]));
        return response()->json(['id' => $id, 'message' => 'Submitted. It will appear after verification.'], 201);
    }

    private function mine(Request $r, int $id): object { return DB::table('directory_entries')->where(['id' => $id, 'owner_user_id' => $r->user()->id])->first() ?? abort(404); }

    public function mineList(Request $r) { return DB::table('directory_entries')->where('owner_user_id', $r->user()->id)->orderByDesc('id')->get(['id', 'kind', 'name', 'status', 'verified', 'listing_until', 'views']); }

    public function saveRoom(Request $r, int $id, ?int $room = null)
    {
        $this->mine($r, $id);
        $d = $r->validate(['name' => 'required|string|max:120', 'kind' => 'required|in:hall,room,dorm', 'capacity' => 'nullable|integer|min:0|max:100000', 'rate' => 'required|numeric|min:0', 'currency' => 'nullable|string|size:3', 'is_active' => 'sometimes|boolean']);
        $row = array_merge($d, ['entry_id' => $id, 'capacity' => $d['capacity'] ?? 0, 'currency' => strtoupper($d['currency'] ?? 'INR')]);
        if ($room) { abort_unless(DB::table('directory_rooms')->where(['id' => $room, 'entry_id' => $id])->update($row), 404); return ['id' => $room]; }
        return response()->json(['id' => DB::table('directory_rooms')->insertGetId($row)], 201);
    }

    public function ownerBookings(Request $r, int $id)
    {
        $this->mine($r, $id);
        return DB::table('directory_bookings as b')->join('users as u', 'u.id', '=', 'b.user_id')->leftJoin('directory_rooms as rm', 'rm.id', '=', 'b.room_id')->where('b.entry_id', $id)->orderByDesc('b.id')->limit(100)
            ->get(['b.id', 'u.name', 'b.from_date', 'b.to_date', 'b.guests', 'b.purpose', 'b.status', 'b.response', 'rm.name as room']);
    }

    public function decideBooking(Request $r, int $id, int $bid)
    {
        $this->mine($r, $id);
        $d = $r->validate(['decision' => 'required|in:confirmed,rejected', 'response' => 'nullable|string|max:250']);
        $b = DB::table('directory_bookings')->where(['id' => $bid, 'entry_id' => $id, 'status' => 'enquiry'])->first() ?? abort(404);
        if ($d['decision'] === 'confirmed') {
            $clash = DB::table('directory_bookings')->where('entry_id', $id)->where('status', 'confirmed')->where('id', '!=', $bid)->when($b->room_id, fn ($w, $v) => $w->where('room_id', $v))
                ->where('from_date', '<=', $b->to_date)->where('to_date', '>=', $b->from_date)->exists();
            abort_if($clash, 422, 'Another booking is confirmed on these dates.');
        }
        DB::table('directory_bookings')->where('id', $bid)->update(['status' => $d['decision'], 'response' => $d['response'] ?? null, 'updated_at' => now()]);
        UserNotification::create(['user_id' => $b->user_id, 'title' => 'Your booking enquiry ' . ($d['decision'] === 'confirmed' ? 'has been confirmed' : 'rejected') . (! empty($d['response']) ? ': ' . $d['response'] : '')]);
        return ['message' => 'Updated.'];
    }
}
