<?php

namespace App\Services;

use Mike42\Escpos\Printer;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use Mike42\Escpos\PrintConnectors\FilePrintConnector;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\EscposImage;
use Exception;
use Illuminate\Support\Facades\Log;

class EscPosPrinterService
{
    private $printerName;
    private $config;

    public function __construct()
    {
        $this->printerName = config('printing.default_printer', 'EPSON LX-310 on Computer02');
        $this->config = config('printing', []);
    }

    /**
     * Get printer connector based on configuration
     */
    private function getPrinterConnector()
    {
        try {
            // Method 1: Windows Print Connector (Recommended for your setup)
            if ($this->config['use_windows_connector'] ?? true) {
                return new WindowsPrintConnector($this->printerName);
            }
            
            // Method 2: Network Connector
            if (isset($this->config['network_ip'])) {
                return new NetworkPrintConnector($this->config['network_ip'], 9100);
            }
            
            // Method 3: File/LPT Connector
            $lptPort = $this->config['lpt_port'] ?? 'LPT1';
            return new FilePrintConnector($lptPort);
            
        } catch (Exception $e) {
            Log::error('Failed to create printer connector: ' . $e->getMessage());
            throw new Exception('Cannot connect to printer: ' . $e->getMessage());
        }
    }

    /**
     * Print text content
     */
    public function printText(string $content, array $options = []): bool
    {
        try {
            $connector = $this->getPrinterConnector();
            $printer = new Printer($connector);

            // Set printer modes
            $this->configurePrinter($printer, $options);

            // Print content
            $printer->text($content);
            
            // Add form feed if requested
            if ($options['form_feed'] ?? true) {
                $printer->feed(3);
                $printer->pulse();
            }

            $printer->close();
            return true;

        } catch (Exception $e) {
            Log::error('ESC/POS printing failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Configure printer settings
     */
    private function configurePrinter(Printer $printer, array $options)
    {
        // Initialize printer
        $printer->initialize();

        // Set print mode based on options
        $mode = 0;
        
        if ($options['font_b'] ?? false) {
            $mode |= Printer::MODE_FONT_B;
        }
        
        if ($options['emphasized'] ?? false) {
            $mode |= Printer::MODE_EMPHASIZED;
        }
        
        if ($options['double_height'] ?? false) {
            $mode |= Printer::MODE_DOUBLE_HEIGHT;
        }
        
        if ($options['double_width'] ?? false) {
            $mode |= Printer::MODE_DOUBLE_WIDTH;
        }

        if ($mode > 0) {
            $printer->selectPrintMode($mode);
        }

        // Set text alignment
        $align = $options['align'] ?? 'left';
        switch ($align) {
            case 'center':
                $printer->setJustification(Printer::JUSTIFY_CENTER);
                break;
            case 'right':
                $printer->setJustification(Printer::JUSTIFY_RIGHT);
                break;
            default:
                $printer->setJustification(Printer::JUSTIFY_LEFT);
        }
    }

    /**
     * Print invoice with professional formatting
     */
    public function printInvoice(array $invoiceData): bool
    {
        try {
            $connector = $this->getPrinterConnector();
            $printer = new Printer($connector);

            $printer->initialize();
            
            // Header
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->selectPrintMode(Printer::MODE_DOUBLE_WIDTH);
            $printer->text("INVOICE\n");
            $printer->selectPrintMode(); // Reset
            
            $printer->text(str_repeat("=", 48) . "\n");
            $printer->setJustification(Printer::JUSTIFY_LEFT);
            
            // Invoice details
            $printer->text("Invoice No: " . ($invoiceData['invoice_no'] ?? '') . "\n");
            $printer->text("Date: " . ($invoiceData['date'] ?? date('Y-m-d')) . "\n");
            $printer->text("Customer: " . ($invoiceData['customer'] ?? '') . "\n");
            $printer->text(str_repeat("-", 48) . "\n");
            
            // Items header
            $printer->text(sprintf("%-20s %8s %8s %10s\n", "ITEM", "QTY", "PRICE", "TOTAL"));
            $printer->text(str_repeat("-", 48) . "\n");
            
            // Items
            $total = 0;
            foreach ($invoiceData['items'] ?? [] as $item) {
                $lineTotal = ($item['qty'] ?? 0) * ($item['price'] ?? 0);
                $total += $lineTotal;
                
                $printer->text(sprintf(
                    "%-20s %8s %8s %10s\n",
                    substr($item['name'] ?? '', 0, 20),
                    $item['qty'] ?? '0',
                    number_format($item['price'] ?? 0, 2),
                    number_format($lineTotal, 2)
                ));
            }
            
            // Total
            $printer->text(str_repeat("-", 48) . "\n");
            $printer->setJustification(Printer::JUSTIFY_RIGHT);
            $printer->selectPrintMode(Printer::MODE_EMPHASIZED);
            $printer->text("TOTAL: " . number_format($total, 2) . "\n");
            $printer->selectPrintMode(); // Reset
            
            $printer->feed(3);
            $printer->close();
            
            return true;

        } catch (Exception $e) {
            Log::error('Invoice printing failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Print delivery receipt
     */
    public function printDeliveryReceipt(array $drData): bool
    {
        try {
            $connector = $this->getPrinterConnector();
            $printer = new Printer($connector);

            $printer->initialize();
            
            // Header
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->selectPrintMode(Printer::MODE_DOUBLE_WIDTH);
            $printer->text("DELIVERY RECEIPT\n");
            $printer->selectPrintMode(); // Reset
            
            $printer->text(str_repeat("=", 48) . "\n");
            $printer->setJustification(Printer::JUSTIFY_LEFT);
            
            // DR details
            $printer->text("DR No: " . ($drData['dr_no'] ?? '') . "\n");
            $printer->text("Date: " . ($drData['date'] ?? date('Y-m-d')) . "\n");
            $printer->text("Delivered to: " . ($drData['delivered_to'] ?? '') . "\n");
            $printer->text("Address: " . ($drData['address'] ?? '') . "\n");
            $printer->text(str_repeat("-", 48) . "\n");
            
            // Items header
            $printer->text(sprintf("%-30s %16s\n", "DESCRIPTION", "QUANTITY"));
            $printer->text(str_repeat("-", 48) . "\n");
            
            // Items
            foreach ($drData['items'] ?? [] as $item) {
                $printer->text(sprintf(
                    "%-30s %16s\n",
                    substr($item['description'] ?? '', 0, 30),
                    $item['quantity'] ?? '0'
                ));
            }
            
            // Signature area
            $printer->text("\n\n");
            $printer->text("Received by: ___________________\n");
            $printer->text("Date: ___________\n");
            $printer->text("Signature: ____________________\n");
            
            $printer->feed(3);
            $printer->close();
            
            return true;

        } catch (Exception $e) {
            Log::error('Delivery receipt printing failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Print receipt (simpler format)
     */
    public function printReceipt(array $receiptData): bool
    {
        try {
            $connector = $this->getPrinterConnector();
            $printer = new Printer($connector);

            $printer->initialize();
            
            // Store header
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->selectPrintMode(Printer::MODE_EMPHASIZED);
            $printer->text(($receiptData['store_name'] ?? 'STORE NAME') . "\n");
            $printer->selectPrintMode();
            
            $printer->text(($receiptData['store_address'] ?? '') . "\n");
            $printer->text(str_repeat("-", 32) . "\n");
            $printer->setJustification(Printer::JUSTIFY_LEFT);
            
            // Receipt details
            $printer->text("Receipt: " . ($receiptData['receipt_no'] ?? '') . "\n");
            $printer->text("Date: " . ($receiptData['date'] ?? date('Y-m-d H:i:s')) . "\n");
            $printer->text(str_repeat("-", 32) . "\n");
            
            // Items
            $total = 0;
            foreach ($receiptData['items'] ?? [] as $item) {
                $lineTotal = ($item['qty'] ?? 0) * ($item['price'] ?? 0);
                $total += $lineTotal;
                
                $printer->text($item['name'] . "\n");
                $printer->text(sprintf(
                    "%dx%.2f = %.2f\n",
                    $item['qty'] ?? 0,
                    $item['price'] ?? 0,
                    $lineTotal
                ));
            }
            
            $printer->text(str_repeat("-", 32) . "\n");
            $printer->selectPrintMode(Printer::MODE_EMPHASIZED);
            $printer->text("TOTAL: " . number_format($total, 2) . "\n");
            $printer->selectPrintMode();
            
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->text("\nThank you!\n");
            
            $printer->feed(3);
            $printer->close();
            
            return true;

        } catch (Exception $e) {
            Log::error('Receipt printing failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Test printer connection
     */
    public function testPrinter(): bool
    {
        try {
            $connector = $this->getPrinterConnector();
            $printer = new Printer($connector);
            
            $printer->initialize();
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->selectPrintMode(Printer::MODE_EMPHASIZED);
            $printer->text("PRINTER TEST\n");
            $printer->selectPrintMode();
            
            $printer->setJustification(Printer::JUSTIFY_LEFT);
            $printer->text("Date: " . date('Y-m-d H:i:s') . "\n");
            $printer->text("Printer: " . $this->printerName . "\n");
            $printer->text(str_repeat("-", 32) . "\n");
            $printer->text("If you can read this clearly,\n");
            $printer->text("your printer is working!\n");
            $printer->text(str_repeat("=", 32) . "\n");
            
            // Test different modes
            $printer->text("Normal text\n");
            $printer->selectPrintMode(Printer::MODE_EMPHASIZED);
            $printer->text("Bold text\n");
            $printer->selectPrintMode(Printer::MODE_DOUBLE_HEIGHT);
            $printer->text("Tall text\n");
            $printer->selectPrintMode(Printer::MODE_DOUBLE_WIDTH);
            $printer->text("Wide text\n");
            $printer->selectPrintMode(); // Reset
            
            $printer->feed(3);
            $printer->close();
            
            return true;

        } catch (Exception $e) {
            Log::error('Printer test failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Print custom formatted text
     */
    public function printFormattedText(string $content, array $formatting = []): bool
    {
        try {
            $connector = $this->getPrinterConnector();
            $printer = new Printer($connector);
            
            $printer->initialize();
            
            // Apply formatting
            if ($formatting['center'] ?? false) {
                $printer->setJustification(Printer::JUSTIFY_CENTER);
            }
            
            if ($formatting['bold'] ?? false) {
                $printer->selectPrintMode(Printer::MODE_EMPHASIZED);
            }
            
            if ($formatting['double_width'] ?? false) {
                $printer->selectPrintMode(Printer::MODE_DOUBLE_WIDTH);
            }
            
            if ($formatting['double_height'] ?? false) {
                $printer->selectPrintMode(Printer::MODE_DOUBLE_HEIGHT);
            }
            
            $printer->text($content);
            
            $printer->selectPrintMode(); // Reset
            $printer->setJustification(Printer::JUSTIFY_LEFT);
            
            if ($formatting['feed'] ?? true) {
                $printer->feed(2);
            }
            
            $printer->close();
            
            return true;

        } catch (Exception $e) {
            Log::error('Formatted text printing failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Print barcode (if supported by printer)
     */
    public function printBarcode(string $data, int $type = Printer::BARCODE_CODE39): bool
    {
        try {
            $connector = $this->getPrinterConnector();
            $printer = new Printer($connector);
            
            $printer->initialize();
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            
            // Print barcode
            $printer->barcode($data, $type);
            $printer->text("\n" . $data . "\n");
            
            $printer->feed(2);
            $printer->close();
            
            return true;

        } catch (Exception $e) {
            Log::error('Barcode printing failed: ' . $e->getMessage());
            return false;
        }
    }
}