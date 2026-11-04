<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class Member extends Model
{
    public const ARCHIVE_TYPES = ['Died', 'Married', 'Migrated', 'Naya Chulha', 'Wrong Entry'];

    protected $guarded = [];
    protected $casts = ['extra' => 'array', 'dob' => 'date', 'anniversary' => 'date', 'profile_updated_on' => 'date', 'has_mediclaim' => 'boolean', 'pakshik_member' => 'boolean', 'is_head' => 'boolean', 'archived_at' => 'datetime'];

    protected static function booted(): void
    {
        // Members inherit the area of their family.
        static::addGlobalScope('area', function ($q) {
            $u = auth()->user();
            if (! $u || $u->isSuper() || $u->role === 'member') return;
            $f = $u->areaFilter();
            if (! $f || ! $u->hasArea()) { $q->whereRaw('1 = 0'); return; }
            $q->whereHas('family', fn ($x) => $x->withoutGlobalScopes()->where($f));
        });
        static::creating(function (self $m) {
            $m->member_code ??= self::nextCode();
        });
    }

    public function setAadharAttribute(?string $v): void
    {
        $this->attributes['aadhar_enc'] = $v ? Crypt::encryptString(preg_replace('/\D/', '', $v)) : null;
    }

    public function family() { return $this->belongsTo(Family::class); }

    public function getAgeAttribute(): ?int { return $this->dob?->age; }

    public static function nextCode(): string
    {
        do { $c = (string) random_int(100000, 9999999); } while (static::withoutGlobalScopes()->where('member_code', $c)->exists());
        return $c;
    }
}
