<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEvents;
use App\Models\Attachment;
use App\Models\Event;
use App\Models\MediaItem;
use App\Services\MediaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentationController extends Controller
{
    use AuthorizesEvents;

    public function store(Request $request, Event $event, MediaService $mediaService)
    {
        $this->authorizeWork($event);

        $data = $request->validate([
            'photo' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:15360'],
            'event_phase_id' => ['nullable', 'exists:event_phases,id'],
            'task_id' => ['nullable', 'exists:tasks,id'],
            'caption' => ['nullable', 'string', 'max:200'],
            'captured_at' => ['required', 'date'],
            'gps_lat' => ['required', 'numeric', 'between:-90,90'],
            'gps_lng' => ['required', 'numeric', 'between:-180,180'],
            'gps_accuracy' => ['nullable', 'numeric', 'min:0'],
        ]);

        $media = $mediaService->store($event, $request->file('photo'), $data, auth()->user());

        if ($request->wantsJson()) {
            return response()->json([
                'ok' => true,
                'id' => $media->id,
                'url' => route('media.file', $media),
            ]);
        }

        return back()->with('success', 'Foto dokumentasi diunggah.');
    }

    public function file(MediaItem $media)
    {
        $this->authorizeEvent($media->event);

        $path = $media->displayPath();

        if (! $path || ! Storage::disk('local')->exists($path)) {
            abort(404);
        }

        return Storage::disk('local')->response($path, basename($path), [
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    public function approve(MediaItem $media)
    {
        $this->authorizeReview($media->event);
        $media->update(['status' => 'approved']);

        return back()->with('success', 'Dokumentasi disetujui.');
    }

    public function reject(MediaItem $media)
    {
        $this->authorizeReview($media->event);
        $media->update(['status' => 'rejected']);

        return back()->with('success', 'Dokumentasi ditolak.');
    }

    public function storeAttachment(Request $request, Event $event)
    {
        $this->authorizeWork($event);

        $data = $request->validate([
            'document' => ['required', 'file', 'mimes:pdf,docx,xlsx,jpg,jpeg,png', 'max:25600'],
            'category' => ['required', 'string', 'max:40'],
            'event_phase_id' => ['nullable', 'exists:event_phases,id'],
        ]);

        $file = $request->file('document');
        $ext = $file->getClientOriginalExtension() ?: 'bin';
        $path = $file->storeAs("events/{$event->id}/attachments", Str::uuid().'.'.$ext, 'local');

        Attachment::create([
            'event_id' => $event->id,
            'event_phase_id' => $data['event_phase_id'] ?? null,
            'category' => $data['category'],
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getMimeType() ?: 'application/octet-stream',
            'size' => $file->getSize(),
            'path' => $path,
            'checksum' => hash_file('sha256', Storage::disk('local')->path($path)),
            'uploaded_by' => auth()->id(),
        ]);

        return back()->with('success', 'Lampiran diunggah.');
    }

    public function attachment(Attachment $attachment)
    {
        $this->authorizeEvent($attachment->event);

        if (! Storage::disk('local')->exists($attachment->path)) {
            abort(404);
        }

        return Storage::disk('local')->download($attachment->path, $attachment->original_name);
    }
}
