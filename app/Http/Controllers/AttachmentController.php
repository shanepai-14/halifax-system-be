<?php

namespace App\Http\Controllers;
use App\Models\PurchaseOrder;
use App\Models\ReceivingReport;
use App\Services\AttachmentService;
use Illuminate\Http\Request;

class AttachmentController extends Controller
{
    protected $attachmentService;

    public function __construct(AttachmentService $attachmentService)
    {
        $this->attachmentService = $attachmentService;
    }

    public function uploadPOAttachment(Request $request, string $poNumber)
    {
        $purchaseOrder = PurchaseOrder::where('po_number', $poNumber)->firstOrFail();
        return $this->attachmentService->upload($purchaseOrder, $request, $poNumber);
    }

    public function uploadRRAttachment(Request $request, string $rr_id)
    {
        $report = ReceivingReport::where('rr_id', $rr_id)->firstOrFail();
        return $this->attachmentService->upload($report, $request, $rr_id);
    }

    public function deleteAttachment(int $attachmentId)
    {
        return $this->attachmentService->delete($attachmentId);
    }

    public function getPOAttachments(string $poNumber)
    {
        $purchaseOrder = PurchaseOrder::where('po_number', $poNumber)->firstOrFail();
        return $this->attachmentService->getAttachments($purchaseOrder, $poNumber);
    }

}
