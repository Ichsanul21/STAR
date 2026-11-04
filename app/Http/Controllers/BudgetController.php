<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEvents;
use App\Http\Controllers\Concerns\ChecksVersion;
use App\Models\BudgetItem;
use App\Models\BudgetVersion;
use App\Models\Event;
use App\Services\FinanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BudgetController extends Controller
{
    use AuthorizesEvents, ChecksVersion;

    public function storeVersion(Request $request, Event $event, FinanceService $finance)
    {
        $this->authorizeFinance($event);

        $data = $request->validate([
            'stage' => ['required', 'in:planning,final'],
            'notes' => ['nullable', 'string'],
        ]);

        $version = (int) $event->budgetVersions()->where('stage', $data['stage'])->max('version') + 1;

        $budget = BudgetVersion::create([
            'event_id' => $event->id,
            'stage' => $data['stage'],
            'version' => $version,
            'status' => 'draft',
            'notes' => $data['notes'] ?? null,
        ]);

        $finance->recalcVersion($budget);

        return back()->with('success', 'Versi RAB dibuat.');
    }

    public function import(Request $request, Event $event, FinanceService $finance)
    {
        $this->authorizeFinance($event);

        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx', 'max:10240'],
        ]);

        $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($request->file('file')->getRealPath())->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);

        if (count($rows) < 2) {
            return back()->withErrors(['file' => 'File XLSX kosong.']);
        }

        $normalize = fn ($value) => strtolower(preg_replace('/[^a-z0-9]+/i', '_', trim((string) $value)));
        $header = array_map($normalize, array_shift($rows));
        $index = array_flip($header);

        $find = function (array $names) use ($index) {
            foreach ($names as $name) {
                if (isset($index[$name])) {
                    return $index[$name];
                }
            }

            return null;
        };

        $category = $find(['kategori', 'category']);
        $description = $find(['uraian', 'description', 'item']);
        $qty = $find(['volume', 'qty', 'quantity']);
        $unit = $find(['satuan', 'unit']);
        $price = $find(['harga_satuan', 'unit_price', 'harga']);
        $planned = $find(['jumlah_rencana', 'jumlah', 'total']);

        if ($category === null || $description === null || $qty === null || $unit === null || ($price === null && $planned === null)) {
            return back()->withErrors(['file' => 'Header wajib: kategori, uraian, volume/qty, satuan, harga_satuan/jumlah.']);
        }

        $version = (int) $event->budgetVersions()->where('stage', 'planning')->max('version') + 1;
        $budget = BudgetVersion::create([
            'event_id' => $event->id,
            'stage' => 'planning',
            'version' => $version,
            'status' => 'draft',
            'notes' => 'Imported from XLSX',
        ]);

        $created = 0;
        foreach ($rows as $row) {
            if ($created >= 1000) {
                break;
            }

            $desc = trim((string) ($row[$description] ?? ''));
            if ($desc === '') {
                continue;
            }

            $qtyValue = (float) ($row[$qty] ?? 0);
            $unitPrice = $price !== null ? (float) ($row[$price] ?? 0) : 0;
            $amount = $planned !== null ? (float) ($row[$planned] ?? 0) : $qtyValue * $unitPrice;

            BudgetItem::create([
                'budget_version_id' => $budget->id,
                'category' => trim((string) ($row[$category] ?? 'Lainnya')) ?: 'Lainnya',
                'description' => $desc,
                'qty' => $qtyValue ?: 1,
                'unit' => trim((string) ($row[$unit] ?? 'unit')) ?: 'unit',
                'unit_price' => $unitPrice,
                'planned_amount' => $amount,
                'sort_order' => $created,
            ]);
            $created++;
        }

        $finance->recalcVersion($budget);

        return back()->with('success', "Import RAB selesai: {$created} item.");
    }

    public function storeItem(Request $request, BudgetVersion $budget, FinanceService $finance)
    {
        $this->authorizeFinance($budget->event);

        if ($budget->isFinal() && ! auth()->user()->isDev()) {
            return back()->withErrors(['budget' => 'RAB final sudah dikunci. Buat versi baru untuk revisi.']);
        }

        $data = $request->validate([
            'category' => ['required', 'string', 'max:80'],
            'description' => ['required', 'string', 'max:160'],
            'qty' => ['required', 'numeric', 'min:0.01'],
            'unit' => ['required', 'string', 'max:30'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'task_group_id' => ['nullable', 'exists:task_groups,id'],
            'notes' => ['nullable', 'string'],
        ]);

        BudgetItem::create([
            'budget_version_id' => $budget->id,
            'category' => $data['category'],
            'description' => $data['description'],
            'qty' => $data['qty'],
            'unit' => $data['unit'],
            'unit_price' => $data['unit_price'],
            'planned_amount' => round($data['qty'] * $data['unit_price'], 2),
            'task_group_id' => $data['task_group_id'] ?? null,
            'notes' => $data['notes'] ?? null,
            'sort_order' => (int) $budget->items()->max('sort_order') + 1,
        ]);

        $finance->recalcVersion($budget);

        return back()->with('success', 'Item RAB ditambahkan.');
    }

    public function finalize(Request $request, BudgetVersion $budget, FinanceService $finance)
    {
        $this->authorizeFinance($budget->event);

        if ($this->hasVersionConflict($budget, $request->input('lock_version'))) {
            return $this->conflictResponse($request, 'RAB sudah diubah orang lain. Muat ulang halaman.');
        }

        DB::transaction(function () use ($budget, $finance) {
            if ($budget->stage === 'planning') {
                $existing = $budget->event->budgetVersions()
                    ->where('stage', 'final')
                    ->where('status', 'approved')
                    ->exists();

                if ($existing) {
                    throw new \RuntimeException('Final RAB sudah ada. Buat versi baru untuk revisi.');
                }

                $budget->update([
                    'status' => 'approved',
                    'approved_by' => auth()->id(),
                    'approved_at' => now(),
                ]);
                $budget->increment('lock_version');

                $final = BudgetVersion::create([
                    'event_id' => $budget->event_id,
                    'stage' => 'final',
                    'version' => 1,
                    'status' => 'approved',
                    'approved_by' => auth()->id(),
                    'approved_at' => now(),
                    'notes' => $budget->notes,
                ]);

                foreach ($budget->items as $item) {
                    $final->items()->create([
                        'stable_key' => $item->stable_key,
                        'category' => $item->category,
                        'description' => $item->description,
                        'qty' => $item->qty,
                        'unit' => $item->unit,
                        'unit_price' => $item->unit_price,
                        'planned_amount' => $item->planned_amount,
                        'task_group_id' => $item->task_group_id,
                        'notes' => $item->notes,
                        'sort_order' => $item->sort_order,
                    ]);
                }

                $finance->recalcVersion($final);
            } else {
                $budget->update([
                    'status' => 'approved',
                    'approved_by' => auth()->id(),
                    'approved_at' => now(),
                ]);
                $budget->increment('lock_version');
                $finance->recalcVersion($budget);
            }
        });

        return back()->with('success', 'RAB final disetujui dan dikunci.');
    }
}
