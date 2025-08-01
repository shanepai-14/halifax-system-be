<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;

class DirectLptPrinterService
{
    private $lptPort;
    private $config;

    public function __construct()
    {
        $this->lptPort = config('printing.default_printer', 'LPT1');
        $this->config = config('printing', []);
    }

    /**
     * Print raw text directly to LPT port using PHP file operations
     */
    public function printText(string $content, array $options = []): bool
    {
        try {
            // Format content for dot matrix printing
            $formattedContent = $this->formatForDotMatrix($content, $options);
            
            // Method 1: Direct PHP file write to LPT port
            if ($this->writeToLptPort($formattedContent)) {
                Log::info('Direct LPT write successful');
                return true;
            }
            
            // Method 2: Use Windows copy command as fallback
            if ($this->copyToLptPort($formattedContent)) {
                Log::info('Copy command to LPT successful');
                return true;
            }
            
            // Method 3: Use print command
            if ($this->printCommandToLpt($formattedContent)) {
                Log::info('Print command to LPT successful');
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            Log::error('Direct LPT printing failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Write directly to LPT port using fopen/fwrite
     */
    private function writeToLptPort(string $content): bool
    {
        try {
            Log::info("Attempting direct write to {$this->lptPort}");
            
            // Open LPT port for binary writing
            $handle = fopen($this->lptPort, 'wb');
            
            if (!$handle) {
                Log::warning("Cannot open {$this->lptPort} for writing");
                return false;
            }
            
            // Write content to printer
            $bytesWritten = fwrite($handle, $content);
            fclose($handle);
            
            Log::info("Wrote {$bytesWritten} bytes to {$this->lptPort}");
            
            // Consider it successful if we wrote something
            return $bytesWritten > 0;
            
        } catch (Exception $e) {
            Log::error("Direct LPT write failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Use Windows copy command to send to LPT port
     */
    private function copyToLptPort(string $content): bool
    {
        try {
            // Create temporary file
            $tempFile = tempnam(sys_get_temp_dir(), 'lpt_print_');
            file_put_contents($tempFile, $content);
            
            // Use copy command with binary flag
            $command = sprintf('copy /B "%s" %s', $tempFile, $this->lptPort);
            $output = shell_exec($command . ' 2>&1');
            
            // Clean up
            unlink($tempFile);
            
            // Check if copy was successful
            $success = strpos($output, '1 file(s) copied') !== false;
            
            if ($success) {
                Log::info("Copy command successful: {$output}");
            } else {
                Log::warning("Copy command failed: {$output}");
            }
            
            return $success;
            
        } catch (Exception $e) {
            Log::error("Copy to LPT failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Use Windows print command
     */
    private function printCommandToLpt(string $content): bool
    {
        try {
            // Create temporary file
            $tempFile = tempnam(sys_get_temp_dir(), 'print_cmd_');
            file_put_contents($tempFile, $content);
            
            // Use print command
            $command = sprintf('print /D:%s "%s"', $this->lptPort, $tempFile);
            $output = shell_exec($command . ' 2>&1');
            
            // Clean up
            unlink($tempFile);
            
            // Print command typically returns null on success
            $success = empty($output) || strpos($output, 'is now being printed') !== false;
            
            if ($success) {
                Log::info("Print command successful");
            } else {
                Log::warning("Print command output: {$output}");
            }
            
            return $success;
            
        } catch (Exception $e) {
            Log::error("Print command failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Format content for dot matrix printing
     */
    private function formatForDotMatrix(string $content, array $options = []): string
    {
        $width = $options['width'] ?? 80;
        $addControlCodes = $options['add_control_codes'] ?? true;
        
        $formatted = '';
        
        // Add EPSON initialization if requested
        if ($addControlCodes) {
            $formatted .= "\x1B\x40"; // Initialize printer (ESC @)
        }
        
        // Process content line by line
        $lines = explode("\n", $content);
        foreach ($lines as $line) {
            // Ensure line doesn't exceed width
            if (strlen($line) > $width) {
                $line = substr($line, 0, $width);
            }
            
            $formatted .= $line . "\r\n";
        }
        
        // Add form feed and reset if requested
        if ($addControlCodes) {
            $formatted .= "\f";           // Form feed
            $formatted .= "\x1B\x40";     // Reset printer
        }
        
        return $formatted;
    }

    /**
     * Test printer with verbose output
     */
    public function testPrinter(): array
    {
        try {
            $testContent = $this->generateTestContent();
            
            Log::info("Starting LPT printer test with content length: " . strlen($testContent));
            
            $success = $this->printText($testContent, [
                'width' => 80,
                'add_control_codes' => true
            ]);
            
            return [
                'success' => $success,
                'message' => $success ? 
                    'Test printed successfully - Check your EPSON LX-310!' : 
                    'Test print failed - Check logs for details',
                'printer_port' => $this->lptPort,
                'content_length' => strlen($testContent),
                'methods_tried' => ['direct_write', 'copy_command', 'print_command']
            ];
            
        } catch (Exception $e) {
            Log::error('LPT printer test failed: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Printer test failed: ' . $e->getMessage(),
                'printer_port' => $this->lptPort
            ];
        }
    }

    /**
     * Generate comprehensive test content
     */
    private function generateTestContent(): string
    {
        $content = str_repeat('=', 80) . "\r\n";
        $content .= "DIRECT LPT PRINTER TEST\r\n";
        $content .= str_repeat('=', 80) . "\r\n";
        $content .= "\r\n";
        $content .= "Date/Time: " . date('Y-m-d H:i:s') . "\r\n";
        $content .= "Port: {$this->lptPort}\r\n";
        $content .= "Method: Direct PHP File Operations\r\n";
        $content .= "\r\n";
        $content .= str_repeat('-', 80) . "\r\n";
        $content .= "CHARACTER TEST:\r\n";
        $content .= "ABCDEFGHIJKLMNOPQRSTUVWXYZ\r\n";
        $content .= "abcdefghijklmnopqrstuvwxyz\r\n";
        $content .= "0123456789 !@#$%^&*()_+-=\r\n";
        $content .= str_repeat('-', 80) . "\r\n";
        $content .= "\r\n";
        $content .= "ALIGNMENT TEST:\r\n";
        $content .= sprintf("%-20s %20s %20s %17s\r\n", "LEFT", "CENTER", "RIGHT", "END");
        $content .= sprintf("%-20s %20s %20s %17s\r\n", "Product A", "10", "15.50", "155.00");
        $content .= sprintf("%-20s %20s %20s %17s\r\n", "Product B", "5", "25.00", "125.00");
        $content .= str_repeat('-', 80) . "\r\n";
        $content .= sprintf("%63s %16s\r\n", "TOTAL:", "280.00");
        $content .= "\r\n";
        $content .= str_repeat('=', 80) . "\r\n";
        $content .= "If you can read this clearly, your printer is working!\r\n";
        $content .= str_repeat('=', 80) . "\r\n";
        $content .= "\r\n\r\n\r\n"; // Extra line feeds
        
        return $content;
    }

    /**
     * Print invoice using direct LPT method
     */
    public function printInvoice(array $invoiceData): bool
    {
        try {
            $content = $this->generateInvoiceContent($invoiceData);
            
            return $this->printText($content, [
                'width' => 80,
                'add_control_codes' => true
            ]);
            
        } catch (Exception $e) {
            Log::error('Invoice printing failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Generate invoice content
     */
    private function generateInvoiceContent(array $data): string
    {
        $content = str_repeat('=', 80) . "\r\n";
        $content .= str_pad('INVOICE', 80, ' ', STR_PAD_BOTH) . "\r\n";
        $content .= str_repeat('=', 80) . "\r\n";
        $content .= "\r\n";
        
        $content .= "Invoice No: " . str_pad($data['invoice_no'] ?? '', 25);
        $content .= "Date: " . ($data['date'] ?? date('Y-m-d')) . "\r\n";
        $content .= "Customer: " . ($data['customer'] ?? '') . "\r\n";
        $content .= "\r\n";
        
        $content .= str_pad('ITEM', 35) . str_pad('QTY', 10) . str_pad('PRICE', 15) . "TOTAL\r\n";
        $content .= str_repeat('-', 80) . "\r\n";
        
        $total = 0;
        foreach ($data['items'] ?? [] as $item) {
            $lineTotal = ($item['qty'] ?? 0) * ($item['price'] ?? 0);
            $total += $lineTotal;
            
            $content .= str_pad(substr($item['name'] ?? '', 0, 35), 35);
            $content .= str_pad($item['qty'] ?? '0', 10);
            $content .= str_pad(number_format($item['price'] ?? 0, 2), 15);
            $content .= number_format($lineTotal, 2) . "\r\n";
        }
        
        $content .= str_repeat('-', 80) . "\r\n";
        $content .= str_pad('TOTAL: ' . number_format($total, 2), 80, ' ', STR_PAD_RIGHT) . "\r\n";
        $content .= "\r\n";
        $content .= str_repeat('=', 80) . "\r\n";
        $content .= str_pad('Thank you for your business!', 80, ' ', STR_PAD_BOTH) . "\r\n";
        $content .= str_repeat('=', 80) . "\r\n";
        $content .= "\r\n\r\n\r\n";
        
        return $content;
    }

    /**
     * Print delivery receipt using direct LPT method
     */
    public function printDeliveryReceipt(array $drData): bool
    {
        try {
            $content = $this->generateDRContent($drData);
            
            return $this->printText($content, [
                'width' => 80,
                'add_control_codes' => true
            ]);
            
        } catch (Exception $e) {
            Log::error('Delivery receipt printing failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Generate delivery receipt content
     */
    private function generateDRContent(array $data): string
    {
        $content = str_repeat('=', 80) . "\r\n";
        $content .= str_pad('DELIVERY RECEIPT', 80, ' ', STR_PAD_BOTH) . "\r\n";
        $content .= str_repeat('=', 80) . "\r\n";
        $content .= "\r\n";
        
        $content .= "DR No: " . str_pad($data['dr_no'] ?? '', 30);
        $content .= "Date: " . ($data['date'] ?? date('Y-m-d')) . "\r\n";
        $content .= "Delivered to: " . ($data['delivered_to'] ?? '') . "\r\n";
        $content .= "Address: " . ($data['address'] ?? '') . "\r\n";
        $content .= "\r\n";
        
        $content .= str_pad('DESCRIPTION', 55) . "QUANTITY\r\n";
        $content .= str_repeat('-', 80) . "\r\n";
        
        foreach ($data['items'] ?? [] as $item) {
            $content .= str_pad(substr($item['description'] ?? '', 0, 55), 55);
            $content .= ($item['quantity'] ?? '0') . "\r\n";
        }
        
        $content .= "\r\n\r\n\r\n";
        $content .= "Received by: _________________________    Date: ___________\r\n";
        $content .= "\r\n";
        $content .= "Signature: ___________________________\r\n";
        $content .= "\r\n\r\n\r\n";
        
        return $content;
    }

    /**
     * Check LPT port status
     */
    public function checkLptStatus(): array
    {
        try {
            // Try to get LPT port information
            $modeOutput = shell_exec("mode {$this->lptPort} 2>&1");
            $statusOutput = shell_exec("mode {$this->lptPort} /status 2>&1");
            
            return [
                'port' => $this->lptPort,
                'mode_output' => $modeOutput,
                'status_output' => $statusOutput,
                'accessible' => $this->testPortAccess()
            ];
            
        } catch (Exception $e) {
            return [
                'port' => $this->lptPort,
                'error' => $e->getMessage(),
                'accessible' => false
            ];
        }
    }

    private function testPortAccess(): bool
    {
        try {
            $handle = @fopen($this->lptPort, 'wb');
            if ($handle) {
                fclose($handle);
                return true;
            }
            return false;
        } catch (Exception $e) {
            return false;
        }
    }
}