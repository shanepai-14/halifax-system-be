<?php

namespace App\Http\Controllers;

use App\Services\EscPosPrinterService;
use App\Jobs\PrintDocumentJob;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class PrintController extends Controller
{
    private EscPosPrinterService $printer;

    public function __construct(EscPosPrinterService $printer)
    {
        $this->printer = $printer;
    }

    /**
     * Test printer connection
     */
    public function testPrinter(): JsonResponse
    {
        try {
            $success = $this->printer->testPrinter();
            
            return response()->json([
                'success' => $success,
                'message' => $success ? 'Printer test successful - Check your printer!' : 'Printer test failed',
                'printer' => config('printing.default_printer')
            ]);
            
        } catch (\Exception $e) {
            Log::error('Printer test error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Printer test failed: ' . $e->getMessage(),
                'printer' => config('printing.default_printer')
            ], 500);
        }
    }

    /**
     * Print invoice using ESC/POS
     */
    public function printInvoice(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'invoice_no' => 'required|string',
                'customer' => 'required|string',
                'date' => 'nullable|date',
                'items' => 'required|array',
                'items.*.name' => 'required|string',
                'items.*.qty' => 'required|numeric',
                'items.*.price' => 'required|numeric',
            ]);

            $success = $this->printer->printInvoice($validated);
            
            return response()->json([
                'success' => $success,
                'message' => $success ? 'Invoice printed successfully' : 'Invoice printing failed',
                'data' => $validated
            ]);

        } catch (\Exception $e) {
            Log::error('Invoice printing error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Invoice printing failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Print delivery receipt using ESC/POS
     */
    public function printDeliveryReceipt(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'dr_no' => 'required|string',
                'delivered_to' => 'required|string',
                'address' => 'required|string',
                'date' => 'nullable|date',
                'items' => 'required|array',
                'items.*.description' => 'required|string',
                'items.*.quantity' => 'required|string',
            ]);

            $success = $this->printer->printDeliveryReceipt($validated);
            
            return response()->json([
                'success' => $success,
                'message' => $success ? 'Delivery receipt printed successfully' : 'Delivery receipt printing failed'
            ]);

        } catch (\Exception $e) {
            Log::error('Delivery receipt printing error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Delivery receipt printing failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Print simple receipt
     */
    public function printReceipt(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'receipt_no' => 'required|string',
                'store_name' => 'nullable|string',
                'store_address' => 'nullable|string',
                'date' => 'nullable|string',
                'items' => 'required|array',
                'items.*.name' => 'required|string',
                'items.*.qty' => 'required|numeric',
                'items.*.price' => 'required|numeric',
            ]);

            $success = $this->printer->printReceipt($validated);
            
            return response()->json([
                'success' => $success,
                'message' => $success ? 'Receipt printed successfully' : 'Receipt printing failed'
            ]);

        } catch (\Exception $e) {
            Log::error('Receipt printing error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Receipt printing failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Print custom text with formatting
     */
    public function printText(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'content' => 'required|string',
                'formatting' => 'nullable|array',
                'formatting.center' => 'nullable|boolean',
                'formatting.bold' => 'nullable|boolean',
                'formatting.double_width' => 'nullable|boolean',
                'formatting.double_height' => 'nullable|boolean',
                'formatting.feed' => 'nullable|boolean',
            ]);

            $success = $this->printer->printFormattedText(
                $validated['content'],
                $validated['formatting'] ?? []
            );
            
            return response()->json([
                'success' => $success,
                'message' => $success ? 'Text printed successfully' : 'Text printing failed'
            ]);

        } catch (\Exception $e) {
            Log::error('Text printing error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Text printing failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Print barcode
     */
    public function printBarcode(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'data' => 'required|string',
                'type' => 'nullable|integer'
            ]);

            $success = $this->printer->printBarcode($validated['data'], $validated['type'] ?? null);
            
            return response()->json([
                'success' => $success,
                'message' => $success ? 'Barcode printed successfully' : 'Barcode printing failed'
            ]);

        } catch (\Exception $e) {
            Log::error('Barcode printing error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Barcode printing failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get printer status
     */
    public function getPrinterStatus(): JsonResponse
    {
        try {
            $config = config('printing');
            
            return response()->json([
                'printer_name' => $config['default_printer'],
                'connector_type' => $config['use_windows_connector'] ? 'Windows' : 'Alternative',
                'queue_enabled' => $config['queue_enabled'] ?? false,
                'status' => 'configured'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

        public function findWorkingMethod(): JsonResponse
    {
        $service = new \App\Services\CredentialPrinterService();
        $result = $service->findWorkingMethod();
        
        return response()->json($result);
    }

}