<?php

namespace App\Filament\Resources\SmsMessages\Pages;

use App\Filament\Resources\SmsMessages\SmsMessageResource;
use Filament\Resources\Pages\ListRecords;

class ListSmsMessages extends ListRecords
{
    protected static string $resource = SmsMessageResource::class;

    public function getSubheading(): ?string
    {
        return config('sms.driver') === 'log'
            ? 'No SMS account is connected yet, so these messages were saved here but not sent to phones.'
            : null;
    }
}
