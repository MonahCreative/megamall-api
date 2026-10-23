<?php

namespace App\Filament\Support;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;

/** Runs a business step from an admin button; if a rule says no, shows the reason and keeps the modal open. */
class Attempt
{
    public static function run(Action $action, callable $step): mixed
    {
        try {
            return $step();
        } catch (ValidationException $exception) {
            Notification::make()
                ->danger()
                ->title('Not saved')
                ->body(collect($exception->errors())->flatten()->first())
                ->send();
            $action->halt();
        }

        return null;
    }
}
