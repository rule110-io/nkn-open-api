<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;


class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {

        // The main command that syncs the latest blockchain items
        $schedule->command('blockchain:sync')->everyMinute()->runInBackground();

        // Clean the address book
        $schedule->command('address-book:clean')->everyMinute();

        // Get balance of not-yet-parsed addresses
        // $schedule->command('balances:sync --limit 2000')->everyFiveMinutes();

        // websocket statistics cleanup
        $schedule->command('websockets:clean')->daily();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
