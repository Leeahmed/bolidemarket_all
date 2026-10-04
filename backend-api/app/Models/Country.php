<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    protected $guarded = [];

    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = 'code';

    protected $keyType = 'string';

    protected $casts = ['active' => 'boolean'];

    public function currency()
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }
}
