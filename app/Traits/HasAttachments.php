<?php
namespace App\Traits;

use App\Models\Attachment;
use Illuminate\Support\Str;

trait HasAttachments
{
    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function addAttachment($file, $referenceNumber = null)
    {
        $originalName = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $fileName = uniqid(Str::slug(class_basename($this)) . '_') . '.' . $extension;

        // Create folder structure based on model type and reference number
        $folderPath = strtolower(class_basename($this)) . 's';
        if ($referenceNumber) {
            $folderPath .= '/' . $referenceNumber;
        }

        $path = $file->storeAs($folderPath, $fileName, 'public');

        return $this->attachments()->create([
            'reference_number' => $referenceNumber,
            'file_name' => $originalName,
            'file_path' => $path,
            'file_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize()
        ]);
    }
}
