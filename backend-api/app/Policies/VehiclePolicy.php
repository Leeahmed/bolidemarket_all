<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vehicle;

class VehiclePolicy
{
    public function update(User $user, Vehicle $vehicle): bool
    {
        return $vehicle->shop !== null && (new ShopPolicy)->update($user, $vehicle->shop);
    }

    public function view(User $user, Vehicle $vehicle): bool
    {
        return $this->update($user, $vehicle);
    }

    public function delete(User $user, Vehicle $vehicle): bool
    {
        return $this->update($user, $vehicle);
    }
}
