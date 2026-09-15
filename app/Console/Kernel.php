<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule)
    {
        // Send daily upload reminder at 8:00 AM
        $schedule->command('sales:send-upload-reminder')->dailyAt('08:00');

        // Send order reminders at 9:00 AM daily
        $schedule->command('orders:send-reminders')->dailyAt('09:00');

        // Clean up old logs and temporary files weekly
        $schedule->command('logs:clean')->weekly();
    }

    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');
        require base_path('routes/console.php');
    }

    protected $commands = [
        \App\Console\Commands\ImportProducts::class,
    ];
}
