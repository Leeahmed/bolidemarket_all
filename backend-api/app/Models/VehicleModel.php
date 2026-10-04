<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehicleModel extends Model
{
    protected $guarded = [];

    public $timestamps = false;

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }
}
