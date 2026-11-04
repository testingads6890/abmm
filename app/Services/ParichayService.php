<?php

namespace App\Services;

use App\Models\{Event, MatrimonyProfile, User};
use Illuminate\Support\Facades\DB;

/** Parichay sammelan (online on Zoom / Meet or offline hall): who may register, and who is attending (so families can send interests). */
class ParichayService
{
    /** A paid, visible Parinay profile of the member's family. */
    public function profileOf(User $u): ?MatrimonyProfile
    {
        $ids = DB::table('members')->where('family_id', $u->family_id)->pluck('id');
        return MatrimonyProfile::withoutGlobalScopes()->whereIn('member_id', $ids)->where('status', 'paid')->where(fn ($w) => $w->whereNull('paid_until')->orWhere('paid_until', '>=', today()))->orderByDesc('id')->first();
    }

    /** null = may register, otherwise the message to show */
    public function blockReason(Event $e, User $u): ?string
    {
        if (! $e->parichay || ! $e->profile_required) return null;
        return $this->profileOf($u) ? null : 'A paid profile in Mangal Parinay is required to join this Parichay.';
    }

    public function attendingUserIds(Event $e)
    {
        return DB::table('event_rsvps')->where(['event_id' => $e->id, 'status' => 'going'])->pluck('user_id')
            ->merge(DB::table('ticket_orders')->where(['event_id' => $e->id, 'status' => 'paid'])->pluck('user_id'))
            ->merge(DB::table('event_joins')->where('event_id', $e->id)->pluck('user_id'))->unique()->values();
    }

    /** Profiles of the other registered families (opposite gender, visible, same-gotra excluded) for the acting profile. */
    public function participants(Event $e, MatrimonyProfile $me)
    {
        $users = DB::table('users')->whereIn('id', $this->attendingUserIds($e))->get(['family_id', 'member_id']);
        $mem = DB::table('members')->whereIn('family_id', $users->pluck('family_id')->filter())->pluck('id');
        return MatrimonyProfile::withoutGlobalScopes()->whereIn('member_id', $mem)->where('status', 'paid')->where('visible', true)->where('id', '!=', $me->id)->where('family_id', '!=', $me->family_id)
            ->where('gender', strcasecmp((string) $me->gender, 'male') === 0 ? 'Female' : 'Male')->where(fn ($w) => $w->whereNull('gotra')->orWhere('gotra', '!=', (string) $me->gotra))->get();
    }
}
