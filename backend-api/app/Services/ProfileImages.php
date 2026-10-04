<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ProfileImages
{
    public function store(UploadedFile $file, string $directory): string
    {
        [$bytes, $extension] = app(RasterMetadata::class)->clean($file);
        $key = $directory.'/'.Str::uuid().'.'.$extension;
        if (! Storage::disk('public')->put($key, $bytes)) {
            throw new RuntimeException('Impossible de stocker l’image.');
        }

        return $key;
    }

    public function avatar(User $user, UploadedFile $file): User
    {
        $key = $this->store($file, 'avatars/'.$user->id);
        try {
            return DB::transaction(function () use ($user, $key) {
                $record = User::lockForUpdate()->findOrFail($user->id);
                $previous = $record->avatar_path;
                $record->avatar_path = $key;
                $record->save();
                if ($previous && str_starts_with($previous, 'avatars/'.$user->id.'/')) {
                    DB::afterCommit(function () use ($previous) {
                        try {
                            Storage::disk('public')->delete($previous);
                        } catch (Throwable $e) {
                            report($e);
                        }
                    });
                }

                return $record;
            });
        } catch (Throwable $e) {
            Storage::disk('public')->delete($key);
            throw $e;
        }
    }
}
