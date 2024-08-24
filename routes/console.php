<?php

use App\Console\Commands\SendDailyEmailsCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Schedule::command(SendDailyEmailsCommand::class)->dailyAt('00:00');