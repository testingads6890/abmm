<?php

namespace App\Models;

use App\Models\Concerns\StampsGeo;
use Illuminate\Database\Eloquent\Model;

class JobProfile extends Model
{
    use StampsGeo;

    protected $guarded = [];
    protected $casts = ['open_to_work' => 'boolean'];
}
