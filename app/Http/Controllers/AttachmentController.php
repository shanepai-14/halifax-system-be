<?php

namespace App\Http\Controllers;
use App\Models\PurchaseOrder;
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
