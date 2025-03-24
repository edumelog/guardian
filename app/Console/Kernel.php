<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * These cron jobs are run in the Artisan command line when a server is being used.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule): void
    {
        // ... existing code ...
        
        // Limpa arquivos temporários de preview a cada hora
        $schedule->command('credentials:clean-previews')->hourly();
        
        // Limpa PDFs temporários a cada hora
        $schedule->command('temp:clean-pdfs')
                ->hourly()
                ->withoutOverlapping()
                ->runInBackground();
        
        // ... existing code ...
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
} 