<?php

namespace App\Http\Controllers;

use App\Services\DirectLptPrinterService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class PrintController extends Controller
{
    private DirectLptPrinterService $printer;

    public function __construct(DirectLptPrinterService $printer)
    {
        $this->printer = $printer;
    }

    /**
     * Test printer using direct LPT method
     */
    public function testPrinter(): JsonResponse
    {
        try {
            $result = $this->printer->testPrinter();
            
            return response()->json($result, $result['success'] ? 200 : 500);
            
        } catch (\Exception $e) {
            Log::error('Printer test error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Printer test failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Print invoice using direct LPT
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
                'message' => $success ? 'Invoice sent to printer - Check EPSON LX-310!' : 'Invoice printing failed',
                'data' => $validated,
                'printer_method' => 'Direct LPT'
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
     * Print delivery receipt using direct LPT
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
                'message' => $success ? 'Delivery receipt sent to printer!' : 'Delivery receipt printing failed',
                'printer_method' => 'Direct LPT'
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
     * Print raw text using direct LPT
     */
    public function printText(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'content' => 'required|string',
                'width' => 'nullable|integer|min:40|max:132',
                'add_control_codes' => 'nullable|boolean'
            ]);

            $options = [
                'width' => $validated['width'] ?? 80,
                'add_control_codes' => $validated['add_control_codes'] ?? true
            ];

            $success = $this->printer->printText($validated['content'], $options);
            
            return response()->json([
                'success' => $success,
                'message' => $success ? 'Text sent to printer!' : 'Text printing failed',
                'content_length' => strlen($validated['content']),
                'options' => $options
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
     * Check LPT port status and diagnostics
     */
    public function checkLptStatus(): JsonResponse
    {
        try {
            $status = $this->printer->checkLptStatus();
            
            return response()->json([
                'success' => true,
                'status' => $status,
                'message' => 'LPT port status retrieved'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to check LPT status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Intensive printer test - sends multiple test patterns
     */
    public function intensiveTest(): JsonResponse
    {
        try {
            $results = [];
            
            // Test 1: Basic text
            $basicText = "BASIC TEST - " . date('H:i:s') . "\r\nThis is a basic text test.\r\n\r\n";
            $results['basic_test'] = $this->printer->printText($basicText);
            
            // Test 2: Special characters
            $specialText = "SPECIAL CHARS: !@#$%^&*()_+-=[]{}|;:,.<>?\r\n";
            $results['special_chars'] = $this->printer->printText($specialText);
            
            // Test 3: Line formatting
            $lineTest = str_repeat("-", 80) . "\r\n";
            $lineTest .= "Line formatting test\r\n";
            $lineTest .= str_repeat("=", 80) . "\r\n";
            $results['line_formatting'] = $this->printer->printText($lineTest);
            
            // Test 4: Form feed
            $formFeedTest = "Form feed test\r\n\f";
            $results['form_feed'] = $this->printer->printText($formFeedTest);
            
            $overallSuccess = !in_array(false, $results);
            
            return response()->json([
                'success' => $overallSuccess,
                'message' => $overallSuccess ? 
                    'Intensive test completed - Check your printer for multiple test prints!' : 
                    'Some tests failed - Check logs',
                'test_results' => $results
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Intensive test failed: ' . $e->getMessage()
            ], 500);
        }
    }
}