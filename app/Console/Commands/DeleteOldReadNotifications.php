<?php

namespace App\Console\Commands;

use App\Services\NotificationService;
use Illuminate\Console\Command;


class DeleteOldReadNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:delete-old
                            {--read-days=2 : Number of days to keep read notifications}
                            {--unread-days=7 : Number of days to keep unread notifications}
                            {--dry-run : Run without actually deleting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete old notifications (read: 2+ days, unread: 7+ days by default)';

    /**
     * Notification service instance
     *
     * @var NotificationService
     */
    protected $notificationService;

    /**
     * Create a new command instance.
     */
    public function __construct(NotificationService $notificationService)
    {
        parent::__construct();
        $this->notificationService = $notificationService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $readDays = $this->option('read-days');
        $unreadDays = $this->option('unread-days');
        $dryRun = $this->option('dry-run');
        
        $this->info('Starting cleanup of old notifications...');
        $this->newLine();
        
        // Delete old read notifications
        $this->info("Deleting read notifications older than {$readDays} days...");
        $readResult = $this->notificationService->deleteOldReadNotifications($readDays, $dryRun);
        
        if ($dryRun && $readResult['count'] > 0) {
            $this->displaySample('Read Notifications', $readResult['sample'], $readResult['count']);
        } elseif ($readResult['count'] > 0) {
            $this->info("✓ Deleted {$readResult['deleted']} read notification(s).");
        } else {
            $this->comment('No old read notifications found.');
        }
        
        $this->newLine();
        
        // Delete old unread notifications
        $this->info("Deleting unread notifications older than {$unreadDays} days...");
        $unreadResult = $this->notificationService->deleteOldUnreadNotifications($unreadDays, $dryRun);
        
        if ($dryRun && $unreadResult['count'] > 0) {
            $this->displaySample('Unread Notifications', $unreadResult['sample'], $unreadResult['count']);
        } elseif ($unreadResult['count'] > 0) {
            $this->info("✓ Deleted {$unreadResult['deleted']} unread notification(s).");
        } else {
            $this->comment('No old unread notifications found.');
        }
        
        $this->newLine();
        
        // Summary
        $totalCount = $readResult['count'] + $unreadResult['count'];
        
        if ($totalCount === 0) {
            $this->info('No old notifications found to delete.');
        } elseif ($dryRun) {
            $this->warn("DRY RUN: Would delete {$totalCount} notification(s) in total.");
            $this->comment('Run without --dry-run to actually delete these notifications.');
        } else {
            $totalDeleted = $readResult['deleted'] + $unreadResult['deleted'];
            $this->info("Successfully deleted {$totalDeleted} notification(s) in total.");
        }
        
        return Command::SUCCESS;
    }

    /**
     * Display sample of notifications
     *
     * @param string $type
     * @param array $sample
     * @param int $total
     * @return void
     */
    protected function displaySample(string $type, array $sample, int $total): void
    {
        if (empty($sample)) {
            return;
        }
        
        $this->warn("Found {$total} {$type} to delete:");
        $this->table(
            ['ID', 'User ID', 'Title', 'Type', 'Created At'],
            collect($sample)->map(fn($n) => [
                $n['id'],
                $n['user_id'],
                $n['title'],
                $n['type'],
                $n['created_at']
            ])
        );
        
        if ($total > count($sample)) {
            $this->comment("... and " . ($total - count($sample)) . " more.");
        }
    }
}