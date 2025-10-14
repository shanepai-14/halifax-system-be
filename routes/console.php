<?php

use Illuminate\Support\Facades\Schedule;


Schedule::command('sales:rebuild-summaries')
    ->dailyAt('09:00');

Schedule::command('notifications:delete-old')
    ->dailyAt('09:00');
