<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEvents;
use App\Models\Event;
use App\Models\ImportBatch;
use App\Services\XlsxImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class XlsxImportController extends Controller
{
    use AuthorizesEvents;

    public function preview(Request $request, Event $event, XlsxImportService $service)
    {
        $this->authorizeManage($event);

        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx', 'max:10240'],
            'type' => ['required', 'in:rab,rundown,planning'],
        ]);

        $path = $request->file('file')->storeAs('imports', Str::uuid().'.xlsx', 'local');

        $batch = ImportBatch::create([
            'event_id' => $event->id,
            'user_id' => auth()->id(),
            'type' => $data['type'],
            'path' => $path,
            'status' => 'preview',
        ]);

        return redirect()->route('imports.map', $batch);
    }

    public function map(ImportBatch $batch, XlsxImportService $service)
    {
        $this->authorizeManage($batch->event);

        $absolute = Storage::disk('local')->path($batch->path);
        if (! is_file($absolute)) {
            return redirect()->route('events.show', $batch->event)->withErrors(['file' => 'File import tidak ditemukan.']);
        }

        $parsed = $service->parse($absolute);
        $targets = $service->targets($batch->type);
        $suggested = $service->suggest($batch->type, $parsed['headers']);
        $sample = array_slice($parsed['rows'], 0, 5);

        return view('imports.map', compact('batch', 'parsed', 'targets', 'suggested', 'sample'));
    }

    public function commit(Request $request, ImportBatch $batch, XlsxImportService $service)
    {
        $this->authorizeManage($batch->event);

        $data = $request->validate([
            'mapping' => ['required', 'array'],
            'mapping.*' => ['nullable', 'string'],
        ]);

        $count = $service->import($batch, array_filter($data['mapping'], fn ($value) => $value !== null && $value !== ''));

        Storage::disk('local')->delete($batch->path);
        $batch->update([
            'status' => 'committed',
            'mapping' => $data['mapping'],
        ]);

        return redirect()->route('events.show', $batch->event)
            ->with('success', "Import selesai: {$count} baris.");
    }
}
