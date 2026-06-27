<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BidAttachmentResource;
use App\Models\Bid;
use App\Models\BidAttachment;
use App\Traits\LogsActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BidAttachmentController extends Controller
{
    use LogsActivity;

    public function store(Request $request, Bid $bid)
    {
        $request->validate([
            'file' => 'required|file|mimes:jpeg,png,jpg,gif,webp,pdf|max:20480',
        ]);

        $file = $request->file('file');
        $path = $file->store("bid-attachments/{$bid->id}", 'public');

        $attachment = BidAttachment::create([
            'bid_id'        => $bid->id,
            'file_path'     => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type'     => $file->getMimeType(),
            'file_size'     => $file->getSize(),
        ]);

        $this->logActivity(
            action:      'CREATE',
            module:      'Bid Tracker',
            description: "Uploaded attachment '{$file->getClientOriginalName()}' to bid: {$bid->bid_number}",
        );

        return response()->json([
            'message'    => 'Attachment uploaded successfully',
            'attachment' => new BidAttachmentResource($attachment),
        ], 201);
    }

    public function destroy(Bid $bid, BidAttachment $attachment)
    {
        if ($attachment->bid_id !== $bid->id) {
            return response()->json(['message' => 'Not found'], 404);
        }

        Storage::disk('public')->delete($attachment->file_path);

        $this->logActivity(
            action:      'DELETE',
            module:      'Bid Tracker',
            description: "Deleted attachment '{$attachment->original_name}' from bid: {$bid->bid_number}",
        );

        $attachment->delete();

        return response()->json(['message' => 'Attachment deleted successfully']);
    }
}
