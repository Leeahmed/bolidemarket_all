<?php

namespace App\Services\Commerce;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CommerceAudit
{
    public static function record(User $actor, Model $record, string $action): void
    {
        DB::table('commerce_events')->insert(['actor_id' => $actor->id, 'resource_type' => $record->getTable(), 'resource_id' => $record->id, 'action' => $action, 'is_demo' => true, 'created_at' => now()]);
    }
}
