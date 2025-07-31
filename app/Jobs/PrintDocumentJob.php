<?php

namespace App\Jobs;

use App\Services\DotMatrixPrinterService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PrintDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;

    private string $content;
    private array $options;
    private string $documentType;
    private array $metadata;

    public function __construct(string $content, array $options = [], string $documentType = 'text', array $metadata = [])
    {
        $this->content = $content;
        $this->options = $options;
        $this->documentType = $documentType;
        $this->metadata = $metadata;
        
        // Set queue name from config
        $this->onQueue(config('printing.queue_name', 'printing'));
    }

    public function handle(DotMatrixPrinterService $printer): void
    {
        Log::info('Processing print job', [
            'type' => $this->documentType,
            'metadata' => $this->metadata,
            'attempt' => $this->attempts()
        ]);

        try {
            $success = $printer->printText($this->content, $this->options);
            
            if (!$success) {
                throw new \Exception('Printing failed');
            }
            
            Log::info('Print job completed successfully', ['metadata' => $this->metadata]);
            
        } catch (\Exception $e) {
            Log::error('Print job failed', [
                'error' => $e->getMessage(),
                'metadata' => $this->metadata,
                'attempt' => $this->attempts()
            ]);
            
            // Re-throw to trigger retry mechanism
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Print job failed permanently', [
            'error' => $exception->getMessage(),
            'metadata' => $this->metadata,
            'attempts' => $this->attempts()
        ]);
    }
}