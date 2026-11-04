<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserNotification extends Model
{
    protected $table = 'user_notifications';
    protected $guarded = [];
    protected $casts = ['read_at' => 'datetime'];

    /** Header-bell entry for every active admin. */
    public static function notifyAdmins(string $title, ?string $url = null, ?string $pradesh = null): void
    {
        User::where('is_active', true)->where(fn ($q) => $q->where('role', 'super_admin')
            ->orWhere(fn ($w) => $w->where('role', 'state_admin')->when($pradesh, fn ($x) => $x->where('pradesh', $pradesh))))->pluck('id')
            ->each(fn ($id) => static::create(['user_id' => $id, 'title' => $title, 'url' => $url]));
    }
}
