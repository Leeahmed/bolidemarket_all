<?php

namespace App\Services\Commerce;

use App\Exceptions\CommerceConflict;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class IdempotencyService
{
    public function run(User $user, string $scope, string $key, array $payload, string $model, callable $action): array
    {
        ksort($payload);
        $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($user, $scope, $key, $hash, $model, $action) {
            $identity = ['user_id' => $user->id, 'scope' => $scope, 'key' => $key];
            DB::table('idempotency_keys')->insertOrIgnore($identity + ['request_hash' => $hash, 'created_at' => now(), 'updated_at' => now()]);
            $entry = DB::table('idempotency_keys')->where($identity)->lockForUpdate()->first();
            if (! hash_equals($entry->request_hash, $hash)) {
                throw new CommerceConflict('IDEMPOTENCY_CONFLICT', 'Cette clé a déjà été utilisée pour une autre demande.');
            }
            if ($entry->resource_id) {
                return [$model::findOrFail($entry->resource_id), true];
            }
            $resource = $action();
            DB::table('idempotency_keys')->where('id', $entry->id)->update(['resource_id' => $resource->id, 'updated_at' => now()]);

            return [$resource, false];
        }, 3);
    }
}
