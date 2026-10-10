<?php

namespace App\Services;

use App\Enums\MerchantApproval;
use App\Enums\PublicationStatus;
use App\Enums\ShopStatus;
use App\Enums\UserRole;
use App\Models\AdminActivityLog;
use App\Models\MerchantProfile;
use App\Models\Shop;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminModerationService
{
    public function act(User $admin, string $type, int $id, string $action, string $reason): void
    {
        abort_unless($admin->role === UserRole::ADMIN && $admin->disabled_at === null, 403);
        DB::transaction(function () use ($admin, $type, $id, $action, $reason) {
            $model = match ($type) {
                'users' => User::class, 'merchants' => MerchantProfile::class,
                'shops' => Shop::class, 'vehicles' => Vehicle::class,
            };
            $subject = $model::findOrFail($id);
            // Use the same parent vehicle locks as commerce, before locking the owner/shop.
            $vehicles = match ($type) {
                'users' => Vehicle::whereHas('shop.merchant', fn ($q) => $q->where('owner_user_id', $id)),
                'merchants' => Vehicle::whereHas('shop', fn ($q) => $q->where('merchant_id', $id)),
                'shops' => Vehicle::where('shop_id', $id),
                'vehicles' => Vehicle::whereKey($id),
            };
            $locked = $vehicles->orderBy('id')->lockForUpdate()->get();
            $wasPublic = Vehicle::publiclyVisible()->whereIn('id', $locked->modelKeys())->pluck('id')->all();
            $subject = $model::lockForUpdate()->findOrFail($id);
            $field = match ($type) {
                'users' => 'disabled_at', 'merchants' => 'approval_status', 'shops' => 'status', 'vehicles' => 'publication_status'
            };
            $before = $subject->getRawOriginal($field);
            if ($type === 'users') {
                if ($action === 'suspend' && $subject->id === $admin->id) {
                    throw ValidationException::withMessages(['action' => ['Vous ne pouvez pas suspendre votre propre compte.']]);
                }
                $subject->disabled_at = $action === 'suspend' ? now() : null;
                if ($action === 'suspend') {
                    $subject->tokens()->delete();
                    DB::table('sessions')->where('user_id', $id)->delete();
                }
            } elseif ($type === 'merchants') {
                $subject->approval_status = match ($action) {
                    'approve', 'reactivate' => MerchantApproval::APPROVED,
                    'suspend' => MerchantApproval::SUSPENDED,
                };
            } elseif ($type === 'shops') {
                if ($action === 'reactivate' && ! ($subject->merchant->approval_status === MerchantApproval::APPROVED && $subject->merchant->owner->disabled_at === null)) {
                    throw ValidationException::withMessages(['action' => ['Le professionnel doit être actif et approuvé.']]);
                }
                $subject->status = $action === 'suspend' ? ShopStatus::SUSPENDED : ShopStatus::PUBLISHED;
            } else {
                $shop = Shop::lockForUpdate()->findOrFail($subject->shop_id);
                if ($action === 'publish') {
                    // Admin can lift moderation but cannot bypass normal publication requirements.
                    $subject->moderation_status = 'clear';
                    app(VehicleService::class)->assertPublishable($subject);
                    $subject->publication_status = PublicationStatus::PUBLISHED;
                    $subject->published_at ??= now();
                } elseif ($action === 'review') {
                    if ($subject->moderation_status === 'suspended') {
                        throw ValidationException::withMessages(['action' => ['Une suspension doit être levée explicitement par une republication autorisée.']]);
                    }
                    $subject->moderation_status = 'review';
                } else {
                    $subject->publication_status = PublicationStatus::DRAFT;
                    // Both actions are persistent: Pro cannot undo administrative unpublication.
                    $subject->moderation_status = 'suspended';
                }
                $subject->version++;
            }
            $subject->save();
            AdminActivityLog::create(['admin_id' => $admin->id, 'action' => $type.'.'.$action,
                'subject_type' => $type, 'subject_id' => $id, 'reason' => $reason,
                'metadata' => ['before' => $before, 'after' => $subject->getRawOriginal($field)], 'created_at' => now()]);
            // Existing publisher guarantees after-commit delivery and tombstones on removal.
            if ($type !== 'vehicles') {
                foreach ($locked as $vehicle) {
                    app(RealtimePublisher::class)->vehicle($vehicle, 'VehicleUpdated', in_array($vehicle->id, $wasPublic), ['shop']);
                }
            }
        }, 3);
    }
}
