<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventDivision;
use App\Models\EventPhase;
use App\Models\Task;
use App\Models\TaskGroup;

class EventSetupService
{
    public function bootstrap(Event $event): void
    {
        if ($event->phases()->exists()) {
            return;
        }

        $divisions = collect([
            ['name' => 'Sales', 'code' => 'sales'],
            ['name' => 'Marketing', 'code' => 'marketing'],
            ['name' => 'Dokumentasi', 'code' => 'documentation'],
            ['name' => 'Operasional', 'code' => 'operations'],
            ['name' => 'Finance', 'code' => 'finance'],
        ])->mapWithKeys(fn ($d, $i) => [
            $d['code'] => EventDivision::create([
                'event_id' => $event->id,
                'name' => $d['name'],
                'code' => $d['code'],
                'sort_order' => $i,
            ]),
        ]);

        $blueprint = [
            'prep' => [
                'name' => 'Persiapan',
                'weight' => 1,
                'groups' => [
                    ['name' => 'Perencanaan & Perizinan', 'division' => 'operations', 'tasks' => [
                        ['Finalisasi RAB', 'finance', 'high'],
                        ['Konfirmasi venue', 'operations', 'high'],
                        ['Ajukan perizinan', 'operations', 'medium'],
                    ]],
                    ['name' => 'Produksi & Logistik', 'division' => 'operations', 'tasks' => [
                        ['Produksi booth', 'operations', 'high'],
                        ['Checklist logistik', 'operations', 'medium'],
                    ]],
                    ['name' => 'Tim & Briefing', 'division' => 'marketing', 'tasks' => [
                        ['Briefing tim', 'marketing', 'medium'],
                        ['Finalisasi materi promosi', 'marketing', 'medium'],
                    ]],
                ],
            ],
            'event' => [
                'name' => 'Hari-H',
                'weight' => 2,
                'groups' => [
                    ['name' => 'Eksekusi Event', 'division' => 'operations', 'tasks' => [
                        ['Opening event', 'operations', 'high'],
                        ['Operasional booth', 'operations', 'high'],
                    ]],
                    ['name' => 'Dokumentasi', 'division' => 'documentation', 'tasks' => [
                        ['Capture dokumentasi fase', 'documentation', 'high'],
                        ['Cek kelengkapan dokumentasi', 'documentation', 'high'],
                    ]],
                    ['name' => 'Sales & Lead', 'division' => 'sales', 'tasks' => [
                        ['Rekap pengunjung', 'sales', 'high'],
                        ['Follow-up lead', 'sales', 'medium'],
                    ]],
                ],
            ],
            'post' => [
                'name' => 'Pasca-Event',
                'weight' => 1,
                'groups' => [
                    ['name' => 'Closing', 'division' => 'operations', 'tasks' => [
                        ['Teardown', 'operations', 'medium'],
                        ['Rekap penjualan', 'sales', 'high'],
                    ]],
                    ['name' => 'Reporting', 'division' => 'documentation', 'tasks' => [
                        ['Susun laporan', 'documentation', 'high'],
                        ['Review laporan', 'documentation', 'high'],
                        ['Publish laporan', 'documentation', 'high'],
                    ]],
                ],
            ],
        ];

        $order = 0;
        foreach ($blueprint as $code => $phaseData) {
            $phase = EventPhase::create([
                'event_id' => $event->id,
                'name' => $phaseData['name'],
                'code' => $code,
                'sort_order' => $order++,
                'weight' => $phaseData['weight'],
                'start_date' => $event->start_date,
                'end_date' => $event->end_date,
            ]);

            foreach ($phaseData['groups'] as $groupIndex => $groupData) {
                $group = TaskGroup::create([
                    'event_id' => $event->id,
                    'event_phase_id' => $phase->id,
                    'event_division_id' => $divisions[$groupData['division']]->id ?? null,
                    'name' => $groupData['name'],
                    'weight' => 1,
                    'sort_order' => $groupIndex,
                ]);

                foreach ($groupData['tasks'] as $taskIndex => $taskData) {
                    Task::create([
                        'event_id' => $event->id,
                        'task_group_id' => $group->id,
                        'name' => $taskData[0],
                        'weight' => 1,
                        'priority' => $taskData[2],
                        'sort_order' => $taskIndex,
                        'due_at' => $event->start_date?->copy()->subDays(max(0, 3 - $taskIndex)),
                    ]);
                }
            }
        }
    }
}
