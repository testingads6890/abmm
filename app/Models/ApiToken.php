<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ApiToken extends Model
{
    protected $guarded = [];
    protected $casts = ['expires_at' => 'datetime', 'last_used_at' => 'datetime'];

    public function user() { return $this->belongsTo(User::class); }

    /** Returns [model, plainToken]. Only the SHA-256 hash is stored. */
    public static function issue(User $u, ?string $device = null, ?string $fcm = null, int $days = 30): array
    {
        $plain = Str::random(60);
        $m = static::create([
            'user_id' => $u->id, 'token_hash' => hash('sha256', $plain), 'device_name' => $device ? Str::limit($device, 100, '') : null,
            'fcm_token' => $fcm, 'expires_at' => now()->addDays($days),
        ]);
        return [$m, $m->id . '|' . $plain];
    }
}
