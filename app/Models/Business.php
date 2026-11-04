<?php

namespace App\Models;

use App\Models\Concerns\HasArea;
use Illuminate\Database\Eloquent\Model;

class Business extends Model
{
    use HasArea;

    protected static array $areaColumns = ['pradesh', 'district'];
    protected $guarded = [];
    protected $table = 'businesses';
    public function reviews() { return $this->hasMany(BusinessReview::class); }
    public function ads() { return $this->hasMany(BusinessAd::class); }
    public function family() { return $this->belongsTo(Family::class); }
}
