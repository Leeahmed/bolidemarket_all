<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class AdminActivityLog extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = ['metadata' => 'array', 'created_at' => 'immutable_datetime'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Journal administratif immuable.'));
        static::deleting(fn () => throw new LogicException('Journal administratif conservé.'));
    }

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
