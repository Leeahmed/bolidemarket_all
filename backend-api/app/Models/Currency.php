<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    protected $guarded = [];

    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = 'code';

    protected $keyType = 'string';

    protected $casts = ['active' => 'boolean', 'minor_unit' => 'integer'];
}
