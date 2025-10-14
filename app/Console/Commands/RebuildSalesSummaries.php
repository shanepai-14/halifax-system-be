<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\SalesSummaryService;
use Illuminate\Support\Facades\Log;

class RebuildSalesSummaries extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'sales:rebuild-summaries';

    /**
     * The console command description.
     */
    protected $description = 'Rebuild all sales summaries from scratch';

    /**
     * Execute the console command.
     */
    public function handle(SalesSummaryService $summaryService)
    {
        $this->info('Starting sales summaries rebuild...');
        
        try {
            $startTime = microtime(true);
            
            $summaryService->rebuildAllSummaries();
            
            $endTime = microtime(true);
            $duration = round($endTime - $startTime, 2);
            
            $this->info("Sales summaries rebuilt successfully in {$duration} seconds.");

            Log::info('Sales summaries rebuilt successfully', [
                'duration' => $duration
            ]);
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error('Failed to rebuild sales summaries: ' . $e->getMessage());

            Log::error('Failed to rebuild sales summaries', [
                'error' => $e->getMessage()
            ]);
            
            return Command::FAILURE;
        }
    }
}