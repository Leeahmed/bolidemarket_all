<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehicleImage extends Model
{
    protected $fillable = ['storage_key', 'alt_text', 'position', 'is_placeholder'];

    protected $casts = ['position' => 'integer', 'is_placeholder' => 'boolean'];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }
}
