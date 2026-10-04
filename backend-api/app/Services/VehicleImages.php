<?php

namespace App\Services;

use App\Enums\PublicationStatus;
use App\Models\Vehicle;
use App\Models\VehicleImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class VehicleImages
{
    public function add(Vehicle $vehicle, UploadedFile $file, ?string $alt): VehicleImage
    {
        [$bytes, $extension] = (new RasterMetadata)->clean($file);
        $key = 'vehicles/'.$vehicle->id.'/'.Str::uuid().'.'.$extension;
        Storage::disk('public')->put($key, $bytes);
        try {
            return DB::transaction(function () use ($vehicle, $key, $alt) {
                $vehicle = Vehicle::lockForUpdate()->findOrFail($vehicle->id);
                Gate::authorize('update', $vehicle);
                (new VehicleService)->assertEditable($vehicle);
                if ($vehicle->images()->count() >= 20) {
                    throw ValidationException::withMessages(['image' => ['Maximum 20 images par véhicule.']]);
                }
                $position = $vehicle->images()->max('position');

                return $vehicle->images()->create(['storage_key' => $key, 'alt_text' => $alt, 'position' => $position === null ? 0 : $position + 1]);
            });
        } catch (Throwable $e) {
            Storage::disk('public')->delete($key);
            throw $e;
        }
    }

    public function primary(Vehicle $vehicle, VehicleImage $image): VehicleImage
    {
        return DB::transaction(function () use ($vehicle, $image) {
            $vehicle = Vehicle::lockForUpdate()->findOrFail($vehicle->id);
            Gate::authorize('update', $vehicle);
            (new VehicleService)->assertEditable($vehicle);
            $image = $vehicle->images()->findOrFail($image->id);
            if ($image->position !== 0) {
                $primary = $vehicle->images()->where('position', 0)->first();
                $position = $image->position;
                if ($primary) {
                    $primary->update(['position' => $vehicle->images()->max('position') + 1]);
                }
                $image->update(['position' => 0]);
                if ($primary) {
                    $primary->update(['position' => $position]);
                }
            }

            return $image;
        });
    }

    public function delete(Vehicle $vehicle, VehicleImage $image): void
    {
        DB::transaction(function () use ($vehicle, $image) {
            $vehicle = Vehicle::lockForUpdate()->findOrFail($vehicle->id);
            Gate::authorize('update', $vehicle);
            (new VehicleService)->assertEditable($vehicle);
            $image = $vehicle->images()->findOrFail($image->id);
            if ($vehicle->publication_status === PublicationStatus::PUBLISHED && $vehicle->images()->count() === 1) {
                throw ValidationException::withMessages(['image' => ['Dépublier le véhicule avant de retirer sa dernière image.']]);
            }
            $key = $image->storage_key;
            $image->delete();
            if ($image->position === 0 && $next = $vehicle->images()->first()) {
                $next->update(['position' => 0]);
            }
            DB::afterCommit(function () use ($key) {
                try {
                    Storage::disk('public')->delete($key);
                } catch (Throwable $e) {
                    report($e);
                }
            });
        });
    }
}
