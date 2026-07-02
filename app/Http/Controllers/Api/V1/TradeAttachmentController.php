<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\TradeAttachmentResource;
use App\Models\Trade;
use App\Models\TradeAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TradeAttachmentController extends Controller
{
    public function store(Request $request, Trade $trade)
    {
        $this->authorize('update', $trade);

        $request->validate([
            'file' => ['required', 'image', 'max:5120'], // 5 MB screenshots
        ]);

        $file = $request->file('file');
        $path = $file->store("trade-screenshots/{$trade->id}", 'public');

        $attachment = $trade->attachments()->create([
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);

        return (new TradeAttachmentResource($attachment))->response()->setStatusCode(201);
    }

    public function destroy(Request $request, TradeAttachment $attachment)
    {
        $this->authorize('update', $attachment->trade);

        Storage::disk('public')->delete($attachment->path);
        $attachment->delete();

        return response()->noContent();
    }
}
