<?php

use Illuminate\Support\Facades\Schedule;

// Release stock held by mobile money orders that were never paid.
Schedule::command('orders:expire-unpaid')->everyFiveMinutes();
