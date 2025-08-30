<?php

namespace App\Console;

use App\Jobs\SyncShopifyProducts;
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
        \App\Console\Commands\ShopifyHealthCommand::class,
        \App\Console\Commands\ShopifyPullCommand::class,
        \App\Console\Commands\ShopifyUpdateOneCommand::class,
        \App\Console\Commands\ShopifyLocationsCommand::class,
        \App\Console\Commands\PullShopifyCategories::class,
        \App\Console\Commands\PullShopifyCollections::class,
        \App\Console\Commands\PushInventoryCommand::class,
        \App\Console\Commands\BackfillInventoryIds::class,
        \App\Console\Commands\InventoryDoctorCommand::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule): void
    {
        // Schedule the Shopify product sync job to run daily at midnight
        $schedule->job(new \App\Jobs\SyncShopifyProducts)->hourly();
        $schedule->command('shopify:bulk-refresh')->dailyAt('02:00');
        $schedule->command('inventory:notify-low-stock')->dailyAt('08:00');
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands(): void
    {
        // Load the commands from the specified directory
        $this->load(__DIR__ . '/Commands');
    }
}
