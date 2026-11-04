<?php

namespace App\Models;

use App\Models\Concerns\HasArea;
use Illuminate\Database\Eloquent\Model;

class CommitteeMember extends Model
{
    use HasArea;

    protected static array $areaColumns = ['pradesh', 'district'];
    protected $guarded = [];
    protected $casts = ['extra' => 'array', 'term_start' => 'date', 'term_end' => 'date', 'dob' => 'date', 'is_active' => 'boolean'];
}
