<?php

namespace App\Http\Controllers;

use App\Jobs\PrintDocumentJob;
use App\Services\DotMatrixPrinterService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PrintController extends Controller
{
    private DotMatrixPrinterService $printer;

    public function __construct(DotMatrixPrinterService $printer)
    {
        $this->printer = $printer;
    }

    /**
     * Print invoice
     */
    public function printInvoice(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'invoice_no' => 'required|string',
            'customer' => 'required|string',
            'date' => 'nullable|date',
            'items' => 'required|array',
            'items.*.name' => 'required|string',
            'items.*.qty' => 'required|numeric',
            'items.*.price' => 'required|numeric',
        ]);

        if (config('printing.queue_enabled')) {
            // Queue the print job
            $content = $this->generateInvoiceContent($validated);
            PrintDocumentJob::dispatch($content, [
                'width' => 80,
                'quality' => 'nlq'
            ], 'invoice', [
                'invoice_no' => $validated['invoice_no'],
                'customer' => $validated['customer']
            ]);
            
            return response()->json(['message' => 'Invoice queued for printing']);
        } else {
            // Print immediately
            $success = $this->printer->printInvoice($validated);
            
            return response()->json([
                'success' => $success,
                'message' => $success ? 'Invoice printed successfully' : 'Printing failed'
            ]);
        }
    }

    /**
     * Print delivery receipt
     */
    public function printDeliveryReceipt(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'dr_no' => 'required|string',
            'delivered_to' => 'required|string',
            'address' => 'required|string',
            'date' => 'nullable|date',
            'items' => 'required|array',
            'items.*.description' => 'required|string',
            'items.*.quantity' => 'required|string',
        ]);

        if (config('printing.queue_enabled')) {
            $content = $this->generateDRContent($validated);
            PrintDocumentJob::dispatch($content, [
                'width' => 80,
                'quality' => 'draft'
            ], 'delivery_receipt', [
                'dr_no' => $validated['dr_no'],
                'delivered_to' => $validated['delivered_to']
            ]);
            
            return response()->json(['message' => 'Delivery receipt queued for printing']);
        } else {
            $success = $this->printer->printDeliveryReceipt($validated);
            
            return response()->json([
                'success' => $success,
                'message' => $success ? 'Delivery receipt printed successfully' : 'Printing failed'
            ]);
        }
    }

    /**
     * Print custom text
     */
    public function printText(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'content' => 'required|string',
            'width' => 'nullable|integer|min:40|max:132',
            'quality' => 'nullable|in:draft,nlq',
            'line_spacing' => 'nullable|integer|min:12|max:72',
        ]);

        $options = array_filter([
            'width' => $validated['width'] ?? null,
            'quality' => $validated['quality'] ?? null,
            'line_spacing' => $validated['line_spacing'] ?? null,
        ]);

        if (config('printing.queue_enabled')) {
            PrintDocumentJob::dispatch($validated['content'], $options, 'text');
            return response()->json(['message' => 'Text queued for printing']);
        } else {
            $success = $this->printer->printText($validated['content'], $options);
            return response()->json([
                'success' => $success,
                'message' => $success ? 'Text printed successfully' : 'Printing failed'
            ]);
        }
    }

    /**
     * Test printer connection
     */
    public function testPrinter(): JsonResponse
    {
        $success = $this->printer->testPrinter();
        
        return response()->json([
            'success' => $success,
            'message' => $success ? 'Printer test successful' : 'Printer test failed'
        ]);
    }

    private function generateInvoiceContent(array $data)
    {
        // Implementation similar to the service method
        // This could be extracted to a separate formatter class
        // return $this->printer->generateInvoiceContent($data);
    }

    private function generateDRContent(array $data)
    {
        // return $this->printer->generateDRContent($data);
    }
}