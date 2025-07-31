<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;

class DotMatrixPrinterService
{
    private string $printerName;
    private array $config;

    public function __construct(string $printerName = null)
    {
        $this->printerName = $printerName ?? config('printing.default_printer', 'EPSON LX-310');
        $this->config = config('printing', []);
    }

    /**
     * Print raw text directly to dot matrix printer
     */
    public function printText(string $content, array $options = []): bool
    {
        try {
            // Format content for dot matrix printing
            $formattedContent = $this->formatForDotMatrix($content, $options);
            
            // Method 1: Try Windows NET USE command first (most reliable)
            if ($this->printViaNetUse($formattedContent)) {
                return true;
            }
            
            // Method 2: Try direct LPT port if available
            if ($this->printViaLptPort($formattedContent)) {
                return true;
            }
            
            // Method 3: Try PowerShell as fallback
            if ($this->printViaPowerShell($formattedContent)) {
                return true;
            }
            
            throw new Exception('All printing methods failed');
            
        } catch (Exception $e) {
            Log::error('Printing failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Format text content for optimal dot matrix printing
     */
    private function formatForDotMatrix(string $content, array $options = []): string
    {
        $width = $options['width'] ?? 80; // Standard 80-column width
        $lineHeight = $options['line_height'] ?? 1;
        
        // Split content into lines
        $lines = explode("\n", $content);
        $formatted = [];
        
        foreach ($lines as $line) {
            // Ensure proper width (pad or truncate)
            if (strlen($line) > $width) {
                // Split long lines
                $wrapped = wordwrap($line, $width, "\n", true);
                $formatted = array_merge($formatted, explode("\n", $wrapped));
            } else {
                $formatted[] = str_pad($line, $width);
            }
            
            // Add extra line spacing if requested
            for ($i = 1; $i < $lineHeight; $i++) {
                $formatted[] = '';
            }
        }
        
        // Add EPSON control codes for better formatting
        $output = $this->addEpsonControlCodes($formatted, $options);
        
        // Ensure final form feed
        $output .= "\f";
        
        return $output;
    }

    /**
     * Add EPSON-specific control codes
     */
    private function addEpsonControlCodes(array $lines, array $options = []): string
    {
        $output = '';
        
        // Initialize printer (ESC @)
        $output .= "\x1B\x40";
        
        // Set print quality if specified
        if (isset($options['quality'])) {
            switch ($options['quality']) {
                case 'draft':
                    $output .= "\x1B\x78\x00"; // Draft quality
                    break;
                case 'nlq':
                    $output .= "\x1B\x78\x01"; // Near Letter Quality
                    break;
            }
        }
        
        // Set font if specified
        if (isset($options['font'])) {
            switch ($options['font']) {
                case 'condensed':
                    $output .= "\x0F"; // Condensed mode
                    break;
                case 'expanded':
                    $output .= "\x0E"; // Expanded mode
                    break;
                case 'bold':
                    $output .= "\x1B\x45"; // Bold mode
                    break;
            }
        }
        
        // Set line spacing (ESC 3 n) - n = 180ths of an inch
        $lineSpacing = $options['line_spacing'] ?? 30; // Default 30/180 inch
        $output .= "\x1B\x33" . chr($lineSpacing);
        
        // Add the actual content
        $output .= implode("\r\n", $lines);
        
        // Reset printer settings
        $output .= "\x1B\x40";
        
        return $output;
    }

    /**
     * Method 1: Print using Windows NET USE command (most reliable)
     */
    private function printViaNetUse(string $content): bool
    {
        try {
            // Create temporary file
            $tempFile = tempnam(sys_get_temp_dir(), 'print_');
            file_put_contents($tempFile, $content);
            
            // Use COPY command to send to printer
            $printerPath = $this->getPrinterPath();
            $command = sprintf('copy /B "%s" "%s"', $tempFile, $printerPath);
            
            $result = shell_exec($command . ' 2>&1');
            unlink($tempFile);
            
            // Check if command was successful
            return strpos($result, '1 file(s) copied') !== false;
            
        } catch (Exception $e) {
            Log::error('NET USE printing failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Method 2: Print directly to LPT port
     */
    private function printViaLptPort(string $content): bool
    {
        try {
            $lptPort = $this->config['lpt_port'] ?? 'LPT1';
            
            // Try to open LPT port directly
            $handle = fopen($lptPort, 'wb');
            if (!$handle) {
                return false;
            }
            
            fwrite($handle, $content);
            fclose($handle);
            
            return true;
            
        } catch (Exception $e) {
            Log::error('LPT port printing failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Method 3: Print using PowerShell
     */
    private function printViaPowerShell(string $content): bool
    {
        try {
            // Create temporary file
            $tempFile = tempnam(sys_get_temp_dir(), 'ps_print_');
            file_put_contents($tempFile, $content);
            
            // PowerShell command to print raw text
            $psCommand = sprintf(
                'powershell -Command "Get-Content \'%s\' -Raw | Out-Printer -Name \'%s\'"',
                $tempFile,
                $this->printerName
            );
            
            $result = shell_exec($psCommand . ' 2>&1');
            unlink($tempFile);
            
            return $result === null; // No output usually means success
            
        } catch (Exception $e) {
            Log::error('PowerShell printing failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get printer path (UNC path or local path)
     */
    private function getPrinterPath(): string
    {
        // Check if it's a network printer
        if (isset($this->config['network_path'])) {
            return $this->config['network_path'];
        }
        
        // Check if it's a local printer with share name
        if (isset($this->config['share_name'])) {
            return '\\\\localhost\\' . $this->config['share_name'];
        }
        
        // Default to LPT1
        return 'LPT1';
    }

    /**
     * Print invoice with predefined format
     */
    public function printInvoice(array $invoiceData): bool
    {
        $content = $this->generateInvoiceContent($invoiceData);
        
        return $this->printText($content, [
            'width' => 80,
            'quality' => 'nlq',
            'line_spacing' => 24
        ]);
    }

    /**
     * Print delivery receipt with predefined format
     */
    public function printDeliveryReceipt(array $drData): bool
    {
        $content = $this->generateDRContent($drData);
        
        return $this->printText($content, [
            'width' => 80,
            'quality' => 'draft',
            'line_spacing' => 30
        ]);
    }

    /**
     * Generate invoice content (customize as needed)
     */
    private function generateInvoiceContent(array $data): string
    {
        $content = str_repeat('=', 80) . "\n";
        $content .= str_pad('INVOICE', 80, ' ', STR_PAD_BOTH) . "\n";
        $content .= str_repeat('=', 80) . "\n\n";
        
        $content .= "Invoice No: " . str_pad($data['invoice_no'] ?? '', 20) . 
                   "Date: " . ($data['date'] ?? date('Y-m-d')) . "\n";
        $content .= "Customer: " . ($data['customer'] ?? '') . "\n\n";
        
        $content .= str_pad('ITEM', 40) . str_pad('QTY', 10) . str_pad('PRICE', 15) . "TOTAL\n";
        $content .= str_repeat('-', 80) . "\n";
        
        $total = 0;
        foreach ($data['items'] ?? [] as $item) {
            $lineTotal = ($item['qty'] ?? 0) * ($item['price'] ?? 0);
            $total += $lineTotal;
            
            $content .= str_pad($item['name'] ?? '', 40);
            $content .= str_pad($item['qty'] ?? '0', 10);
            $content .= str_pad(number_format($item['price'] ?? 0, 2), 15);
            $content .= number_format($lineTotal, 2) . "\n";
        }
        
        $content .= str_repeat('-', 80) . "\n";
        $content .= str_pad('TOTAL: ' . number_format($total, 2), 80, ' ', STR_PAD_RIGHT) . "\n";
        
        return $content;
    }

    /**
     * Generate delivery receipt content
     */
    private function generateDRContent(array $data): string
    {
        $content = str_repeat('=', 80) . "\n";
        $content .= str_pad('DELIVERY RECEIPT', 80, ' ', STR_PAD_BOTH) . "\n";
        $content .= str_repeat('=', 80) . "\n\n";
        
        $content .= "DR No: " . str_pad($data['dr_no'] ?? '', 25) . 
                   "Date: " . ($data['date'] ?? date('Y-m-d')) . "\n";
        $content .= "Delivered to: " . ($data['delivered_to'] ?? '') . "\n";
        $content .= "Address: " . ($data['address'] ?? '') . "\n\n";
        
        $content .= str_pad('DESCRIPTION', 60) . "QUANTITY\n";
        $content .= str_repeat('-', 80) . "\n";
        
        foreach ($data['items'] ?? [] as $item) {
            $content .= str_pad($item['description'] ?? '', 60);
            $content .= ($item['quantity'] ?? '0') . "\n";
        }
        
        $content .= "\n\nReceived by: _____________________    Date: ___________\n";
        $content .= "Signature: _______________________    \n";
        
        return $content;
    }

    /**
     * Test printer connection
     */
    public function testPrinter(): bool
    {
        $testContent = "PRINTER TEST - " . date('Y-m-d H:i:s') . "\n";
        $testContent .= "If you can read this, the printer is working!\n";
        $testContent .= str_repeat('-', 50) . "\n";
        
        return $this->printText($testContent);
    }
}