<?php

namespace App\Services;

use App\Models\Attachment;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
;
use Exception;


class AttachmentService
{
    public function upload($model, Request $request, ?string $referenceNumber = null): JsonResponse
    {
        try {
            $validated = $request->validate([
                'attachments' => 'required|array',
                'attachments.*' => 'required|file|mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png|max:10240',
            ]);

            $uploadedFiles = [];

            foreach ($request->file('attachments') as $file) {
                $attachment = $model->addAttachment($file, $referenceNumber);
                
                $uploadedFiles[] = [
                    'id' => $attachment->id,
                    'file_name' => $attachment->file_name,
                    'file_path' => $attachment->file_path,
                    'file_type' => $attachment->file_type,
                    'file_size' => $attachment->file_size,
                    'created_at' => $attachment->created_at
                ];
            }

            return response()->json([
                'status' => 'success',
                'data' => [
                    'attachments' => $uploadedFiles
                ],
                'message' => 'Attachments uploaded successfully'
            ]);

        } catch (Exception $e) {
            // Cleanup uploaded files on error
            if (!empty($uploadedFiles)) {
                foreach ($uploadedFiles as $file) {
                    Storage::disk('public')->delete($file['file_path']);
                }
            }

            return response()->json([
                'status' => 'error',
                'message' => 'Error uploading attachments',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function delete(int $attachmentId): JsonResponse
    {
        try {
            $attachment = Attachment::findOrFail($attachmentId);
            
            // Delete file from storage
            Storage::disk('public')->delete($attachment->file_path);
            
            // Delete record
            $attachment->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Attachment deleted successfully'
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Attachment not found'
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error deleting attachment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getAttachments($model, ?string $referenceNumber = null): JsonResponse
    {
        try {
            $query = $model->attachments();
            
            if ($referenceNumber) {
                $query->where('reference_number', $referenceNumber);
            }

            $attachments = $query->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($attachment) {
                    return [
                        'id' => $attachment->id,
                        'file_name' => $attachment->file_name,
                        'file_path' => $attachment->file_path,
                        'file_type' => $attachment->file_type,
                        'file_size' => $attachment->file_size,
                        'created_at' => $attachment->created_at
                    ];
                });

            return response()->json([
                'status' => 'success',
                'data' => [
                    'attachments' => $attachments
                ]
            ]);

        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error retrieving attachments',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}