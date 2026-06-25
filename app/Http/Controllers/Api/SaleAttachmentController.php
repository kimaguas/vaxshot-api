<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SaleAttachmentResource;
use App\Models\Sale;
use App\Models\SaleAttachment;
use App\Traits\LogsActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SaleAttachmentController extends Controller
{
    use LogsActivity;

    public function store(Request $request, Sale $sale)
    {
        $request->validate([
            'file' => 'required|file|mimes:jpeg,png,jpg,pdf|max:10240',
        ]);

        $file = $request->file('file');
        $path = $file->store("sale-attachments/{$sale->id}", 'public');

        $attachment = SaleAttachment::create([
            'sale_id'       => $sale->id,
            'file_path'     => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type'     => $file->getMimeType(),
            'file_size'     => $file->getSize(),
        ]);

        $this->logActivity(
            action:      'CREATE',
            module:      'Sales',
            description: "Uploaded attachment '{$file->getClientOriginalName()}' to sale: {$sale->sale_number}",
        );

        return response()->json([
            'message'    => 'Attachment uploaded successfully',
            'attachment' => new SaleAttachmentResource($attachment),
        ], 201);
    }

    public function destroy(Sale $sale, SaleAttachment $attachment)
    {
        if ($attachment->sale_id !== $sale->id) {
            return response()->json(['message' => 'Not found'], 404);
        }

        Storage::disk('public')->delete($attachment->file_path);

        $this->logActivity(
            action:      'DELETE',
            module:      'Sales',
            description: "Deleted attachment '{$attachment->original_name}' from sale: {$sale->sale_number}",
        );

        $attachment->delete();

        return response()->json(['message' => 'Attachment deleted successfully'], 200);
    }
}
