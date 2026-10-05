<?php

namespace App\Observers;

use App\Models\CommerceRecord;
use App\Services\RealtimePublisher;

class CommerceObserver
{
    public function saved(CommerceRecord $record): void
    {
        if (! $record->getRawOriginal('id') || $record->wasChanged('status')) {
            app(RealtimePublisher::class)->commerce($record);
        }
    }
}
