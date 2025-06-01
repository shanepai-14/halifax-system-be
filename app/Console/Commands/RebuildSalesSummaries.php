<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\SalesSummaryService;

class RebuildSalesSummaries extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'sales:rebuild-summaries {--force : Force rebuild without confirmation}';

    /**
     * The console command description.
     */
    protected $description = 'Rebuild all sales summaries from scratch';

    protected $summaryService;

    public function __construct(SalesSummaryService $summaryService)
    {
        parent::__construct();
        $this->summaryService = $summaryService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if (!$this->option('force')) {
            if (!$this->confirm('This will rebuild all sales summaries. Continue?')) {
                $this->info('Operation cancelled.');
                return 0;
            }
        }

        $this->info('Starting sales summaries rebuild...');
        
        try {
            $startTime = microtime(true);
            
            $this->summaryService->rebuildAllSummaries();
            
            $endTime = microtime(true);
            $duration = round($endTime - $startTime, 2);
            
            $this->info("Sales summaries rebuilt successfully in {$duration} seconds.");
            
            return 0;
        } catch (\Exception $e) {
            $this->error('Failed to rebuild sales summaries: ' . $e->getMessage());
            
            return 1;
        }
    }
}