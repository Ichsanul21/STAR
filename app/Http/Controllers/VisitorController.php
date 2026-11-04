<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEvents;
use App\Models\Event;
use App\Models\EventResult;
use App\Models\SalesRecord;
use App\Models\VisitorEntry;
use Illuminate\Http\Request;

class VisitorController extends Controller
{
    use AuthorizesEvents;

    public function store(Request $request, Event $event)
    {
        $this->authorizeWork($event);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:120'],
            'city' => ['nullable', 'string', 'max:80'],
            'province' => ['nullable', 'string', 'max:80'],
            'source' => ['required', 'string', 'max:40'],
            'interest_model' => ['nullable', 'string', 'max:80'],
            'budget_range' => ['nullable', 'string', 'max:80'],
            'payment_method' => ['nullable', 'string', 'max:40'],
            'test_drive' => ['nullable', 'boolean'],
            'lead_status' => ['required', 'in:new,follow_up,hot,warm,cold,closing,lost'],
            'sales_pic' => ['nullable', 'string', 'max:120'],
            'follow_up_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'consent' => ['accepted'],
            'client_uuid' => ['nullable', 'string', 'max:64'],
        ]);

        $data['test_drive'] = $request->boolean('test_drive');
        $data['consent'] = true;
        $data['created_by'] = auth()->id();

        VisitorEntry::create($data);

        $result = EventResult::firstOrCreate(['event_id' => $event->id]);
        $result->update([
            'visitors' => $event->visitors()->count(),
            'unique_visitors' => $event->visitors()->distinct('phone')->count('phone'),
            'leads' => $event->visitors()->where('lead_status', '!=', 'lost')->count(),
            'test_drives' => $event->visitors()->where('test_drive', true)->count(),
        ]);

        if ($request->expectsJson()) {
            session()->flash('success', 'Data pengunjung disimpan.');

            return response()->json(['ok' => true, 'message' => 'Data pengunjung disimpan.']);
        }

        return back()->with('success', 'Data pengunjung disimpan.');
    }

    public function storeSale(Request $request, Event $event)
    {
        $this->authorizeManage($event);

        $data = $request->validate([
            'model' => ['required', 'string', 'max:80'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'spk_number' => ['nullable', 'string', 'max:80'],
            'status' => ['required', 'in:spk,delivered,handover,cancelled'],
            'sold_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        SalesRecord::create(['event_id' => $event->id] + $data);

        $result = EventResult::firstOrCreate(['event_id' => $event->id]);
        $result->update([
            'spk' => $event->sales()->whereIn('status', ['spk', 'delivered', 'handover'])->count(),
            'units_sold' => $event->sales()->whereIn('status', ['delivered', 'handover'])->count(),
            'revenue' => (float) $event->sales()->whereIn('status', ['delivered', 'handover'])->sum('unit_price'),
            'handover' => $event->sales()->where('status', 'handover')->count(),
        ]);

        return back()->with('success', 'Data penjualan disimpan.');
    }

    public function updateResult(Request $request, Event $event)
    {
        $this->authorizeManage($event);

        $data = $request->validate([
            'visitors' => ['nullable', 'integer', 'min:0'],
            'unique_visitors' => ['nullable', 'integer', 'min:0'],
            'leads' => ['nullable', 'integer', 'min:0'],
            'test_drives' => ['nullable', 'integer', 'min:0'],
            'spk' => ['nullable', 'integer', 'min:0'],
            'units_sold' => ['nullable', 'integer', 'min:0'],
            'revenue' => ['nullable', 'numeric', 'min:0'],
            'handover' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $costPerLead = ($data['leads'] ?? 0) > 0
            ? round((float) $event->expenses()->whereIn('status', ['approved', 'paid', 'reimbursed'])->sum('amount') / $data['leads'], 2)
            : 0;
        $revenue = (float) ($data['revenue'] ?? 0);
        $cost = (float) $event->expenses()->whereIn('status', ['approved', 'paid', 'reimbursed'])->sum('amount');
        $roi = $cost > 0 ? round(($revenue - $cost) / $cost * 100, 2) : 0;

        EventResult::updateOrCreate(['event_id' => $event->id], $data + [
            'cost_per_lead' => $costPerLead,
            'roi' => $roi,
        ]);

        return back()->with('success', 'Hasil event diperbarui.');
    }
}
