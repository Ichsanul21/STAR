@php
    $graph = app(\App\Services\DependencyGraphService::class)->build($event);
    $nodeMap = collect($graph['nodes'])->keyBy('id');
@endphp

<section id="graph" class="scroll-mt-24">
    <div class="mb-3 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-xl font-extrabold">Dependency Graph</h2>
            <p class="text-sm" style="color: var(--muted)">Node task, edge dependency, dan critical path. Warna merah = critical path.</p>
        </div>
        <div class="flex items-center gap-2 text-xs">
            <span class="badge badge-danger">Critical path</span>
            <span class="badge badge-muted">{{ count($graph['critical']) }} task</span>
        </div>
    </div>

    @if ($graph['hasCycle'])
        <div class="mb-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            Dependency membentuk siklus. Critical path tidak bisa dihitung sampai siklus diperbaiki.
        </div>
    @endif

    <div class="mb-3 flex flex-wrap items-center gap-2">
        <button id="connectMode" type="button" class="btn btn-secondary btn-sm">Mode Hubungkan</button>
        <select id="dependencyType" class="input !w-auto !py-1.5 text-xs">
            <option value="finish_to_start">Finish → Start</option>
            <option value="start_to_start">Start → Start</option>
            <option value="finish_to_finish">Finish → Finish</option>
            <option value="start_to_finish">Start → Finish</option>
        </select>
        <input id="lagMinutes" type="number" step="1" value="0" class="input !w-24 !py-1.5 text-xs" title="Lag menit">
        <button id="resetGraph" type="button" class="btn btn-ghost btn-sm">Reset Posisi</button>
        <span id="graphHint" class="text-xs" style="color: var(--muted)">Drag node untuk mengatur layout. Mode Hubungkan: klik source lalu target.</span>
    </div>

    <div class="card overflow-x-auto p-4">
        <svg id="graphSvg" data-event-id="{{ $event->id }}" viewBox="0 0 {{ $graph['width'] }} {{ $graph['height'] }}" width="{{ $graph['width'] }}" height="{{ $graph['height'] }}" class="min-w-full">
            <defs>
                <marker id="arrow" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="6" markerHeight="6" orient="auto-start-reverse">
                    <path d="M 0 0 L 10 5 L 0 10 z" fill="#94a3b8"/>
                </marker>
                <marker id="arrow-critical" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="6" markerHeight="6" orient="auto-start-reverse">
                    <path d="M 0 0 L 10 5 L 0 10 z" fill="#b91c1c"/>
                </marker>
            </defs>

            @foreach ($graph['edges'] as $edge)
                @php
                    $from = $nodeMap[$edge['from']] ?? null;
                    $to = $nodeMap[$edge['to']] ?? null;
                @endphp
                @if ($from && $to)
                    @php
                        $x1 = $from['x'] + $from['width'];
                        $y1 = $from['y'] + ($from['height'] / 2);
                        $x2 = $to['x'];
                        $y2 = $to['y'] + ($to['height'] / 2);
                        $mid = ($x1 + $x2) / 2;
                    @endphp
                    <path data-edge data-from="{{ $edge['from'] }}" data-to="{{ $edge['to'] }}"
                          d="M {{ $x1 }} {{ $y1 }} C {{ $mid }} {{ $y1 }}, {{ $mid }} {{ $y2 }}, {{ $x2 }} {{ $y2 }}"
                          fill="none"
                          stroke="{{ $edge['critical'] ? '#b91c1c' : '#94a3b8' }}"
                          stroke-width="{{ $edge['critical'] ? 2.5 : 1.5 }}"
                          stroke-dasharray="{{ $edge['type'] === 'finish_to_start' ? '' : '5 4' }}"
                          marker-end="url(#{{ $edge['critical'] ? 'arrow-critical' : 'arrow' }})"/>
                @endif
            @endforeach

            @foreach ($graph['nodes'] as $node)
                @php
                    $fill = match ($node['status']) {
                        'done' => '#dcfce7',
                        'blocked' => '#fee2e2',
                        'review' => '#fef3c7',
                        'in_progress' => '#e9eff7',
                        default => '#f8fafc',
                    };
                    $stroke = $node['critical'] ? '#b91c1c' : '#cbd5e1';
                    $label = \Illuminate\Support\Str::limit($node['name'], 28);
                @endphp
                <g class="graph-node" data-id="{{ $node['id'] }}" data-x="{{ $node['x'] }}" data-y="{{ $node['y'] }}" style="cursor:grab; touch-action:none">
                    <title>{{ $node['name'] }} · {{ $node['phase'] }} · {{ $node['group'] }} · slack {{ $node['slack'] }} hari</title>
                    <rect data-stroke="{{ $stroke }}" x="{{ $node['x'] }}" y="{{ $node['y'] }}" width="{{ $node['width'] }}" height="{{ $node['height'] }}" rx="12" fill="{{ $fill }}" stroke="{{ $stroke }}" stroke-width="{{ $node['critical'] ? 2.5 : 1 }}"/>
                    <text x="{{ $node['x'] + 12 }}" y="{{ $node['y'] + 20 }}" font-size="11" font-weight="700" fill="#0f172a">{{ $label }}</text>
                    <text x="{{ $node['x'] + 12 }}" y="{{ $node['y'] + 36 }}" font-size="9" fill="#64748b">{{ \Illuminate\Support\Str::limit($node['phase'], 20) }} · {{ $node['progress'] }}%</text>
                    <text x="{{ $node['x'] + 12 }}" y="{{ $node['y'] + 52 }}" font-size="8" fill="{{ $node['critical'] ? '#b91c1c' : '#94a3b8' }}">
                        {{ $node['status'] }} · slack {{ $node['slack'] }}d
                    </text>
                    @if (auth()->user()->canManageEvent($event))
                        <circle class="graph-handle" data-id="{{ $node['id'] }}" cx="{{ $node['x'] + $node['width'] }}" cy="{{ $node['y'] + $node['height'] / 2 }}" r="6" fill="#0a2540" stroke="#ffffff" stroke-width="2" style="cursor:crosshair"/>
                    @endif
                </g>
            @endforeach
        </svg>

        @if (empty($graph['nodes']))
            <x-empty title="Belum ada task" body="Tambahkan task untuk melihat dependency graph." />
        @endif
    </div>

    <div class="mt-4 grid gap-3 md:grid-cols-3">
        <div class="card card-pad"><div class="text-xs font-semibold" style="color: var(--muted)">Total Node</div><div class="mt-1 text-xl font-extrabold">{{ count($graph['nodes']) }}</div></div>
        <div class="card card-pad"><div class="text-xs font-semibold" style="color: var(--muted)">Dependency</div><div class="mt-1 text-xl font-extrabold">{{ count($graph['edges']) }}</div></div>
        <div class="card card-pad"><div class="text-xs font-semibold" style="color: var(--muted)">Critical Path</div><div class="mt-1 text-xl font-extrabold" style="color: var(--danger)">{{ count($graph['critical']) }} task</div></div>
    </div>
</section>

@push('scripts')
<script>
(() => {
    const svg = document.getElementById('graphSvg');
    if (!svg) return;

    const eventId = svg.dataset.eventId;
    const key = `star-graph-${eventId}`;
    const nodes = [...svg.querySelectorAll('.graph-node')];
    const edges = [...svg.querySelectorAll('[data-edge]')];
    const base = {};
    const positions = JSON.parse(localStorage.getItem(key) || '{}');

    nodes.forEach((node) => {
        const rect = node.querySelector('rect');
        base[node.dataset.id] = {
            x: Number(node.dataset.x),
            y: Number(node.dataset.y),
            w: Number(rect.getAttribute('width')),
            h: Number(rect.getAttribute('height')),
        };
    });

    const current = (id) => positions[id] || base[id];

    const applyPositions = () => {
        nodes.forEach((node) => {
            const id = node.dataset.id;
            const p = current(id);
            node.setAttribute('transform', `translate(${p.x - base[id].x}, ${p.y - base[id].y})`);
        });
    };

    const updateEdges = () => {
        edges.forEach((edge) => {
            const from = current(edge.dataset.from);
            const to = current(edge.dataset.to);
            if (!from || !to) return;
            const x1 = from.x + from.w;
            const y1 = from.y + from.h / 2;
            const x2 = to.x;
            const y2 = to.y + to.h / 2;
            const mid = (x1 + x2) / 2;
            edge.setAttribute('d', `M ${x1} ${y1} C ${mid} ${y1}, ${mid} ${y2}, ${x2} ${y2}`);
        });
    };

    applyPositions();
    updateEdges();

    let drag = null;
    nodes.forEach((node) => {
        node.addEventListener('pointerdown', (event) => {
            if (connectMode) return;
            const id = node.dataset.id;
            drag = {
                id,
                startX: event.clientX,
                startY: event.clientY,
                origin: { ...current(id) },
            };
            node.setPointerCapture(event.pointerId);
            node.style.cursor = 'grabbing';
            event.preventDefault();
        });

        node.addEventListener('pointermove', (event) => {
            if (!drag || drag.id !== node.dataset.id) return;
            const box = svg.getBoundingClientRect();
            const view = svg.viewBox.baseVal;
            const dx = (event.clientX - drag.startX) * (view.width / box.width);
            const dy = (event.clientY - drag.startY) * (view.height / box.height);
            positions[drag.id] = { x: drag.origin.x + dx, y: drag.origin.y + dy };
            applyPositions();
            updateEdges();
        });

        node.addEventListener('pointerup', () => {
            if (!drag) return;
            const id = drag.id;
            drag = null;
            node.style.cursor = 'grab';
            localStorage.setItem(key, JSON.stringify(positions));

            const p = positions[id];
            if (!p) return;
            fetch(positionsUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
                body: JSON.stringify({ positions: { [id]: p } }),
            }).then((response) => {
                if (response.ok) localStorage.removeItem(key);
            }).catch(() => {});
        });
    });

    let connectMode = false;
    let connectSource = null;
    const connectButton = document.getElementById('connectMode');
    const hint = document.getElementById('graphHint');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const depUrl = @json(route('tasks.dependencies.store', ['task' => 0]));
    const positionsUrl = @json(route('events.graph.positions', $event));

    const createDependency = (source, target) => {
        const type = document.getElementById('dependencyType').value;
        const lag = document.getElementById('lagMinutes').value || 0;
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = depUrl.replace('/0/', `/${source}/`);
        form.innerHTML = `
            <input type="hidden" name="_token" value="${csrf}">
            <input type="hidden" name="depends_on_task_id" value="${target}">
            <input type="hidden" name="type" value="${type}">
            <input type="hidden" name="lag_minutes" value="${lag}">
        `;
        document.body.appendChild(form);
        form.submit();
    };

    const svgPoint = (event) => {
        const box = svg.getBoundingClientRect();
        const view = svg.viewBox.baseVal;
        return {
            x: (event.clientX - box.left) * (view.width / box.width),
            y: (event.clientY - box.top) * (view.height / box.height),
        };
    };

    const tempEdge = document.createElementNS('http://www.w3.org/2000/svg', 'path');
    tempEdge.setAttribute('fill', 'none');
    tempEdge.setAttribute('stroke', '#0a2540');
    tempEdge.setAttribute('stroke-width', '2');
    tempEdge.setAttribute('stroke-dasharray', '5 4');
    tempEdge.style.display = 'none';
    svg.appendChild(tempEdge);

    let edgeDrag = null;
    const handles = [...svg.querySelectorAll('.graph-handle')];
    handles.forEach((handle) => {
        handle.addEventListener('pointerdown', (event) => {
            event.stopPropagation();
            event.preventDefault();
            edgeDrag = { from: handle.dataset.id };
            handle.setPointerCapture(event.pointerId);
            tempEdge.style.display = 'block';
        });

        handle.addEventListener('pointermove', (event) => {
            if (!edgeDrag) return;
            const from = current(edgeDrag.from);
            const point = svgPoint(event);
            if (!from) return;
            tempEdge.setAttribute('d', `M ${from.x + from.w} ${from.y + from.h / 2} L ${point.x} ${point.y}`);
        });

        handle.addEventListener('pointerup', (event) => {
            if (!edgeDrag) return;
            tempEdge.style.display = 'none';
            const source = edgeDrag.from;
            edgeDrag = null;
            const target = document.elementFromPoint(event.clientX, event.clientY)?.closest('.graph-node');
            if (target && target.dataset.id !== source) {
                createDependency(source, target.dataset.id);
            }
        });
    });

    const clearSelection = () => {
        nodes.forEach((n) => n.querySelector('rect')?.setAttribute('stroke', n.querySelector('rect')?.dataset.stroke));
        connectSource = null;
    };

    connectButton.addEventListener('click', () => {
        connectMode = !connectMode;
        connectButton.classList.toggle('btn-primary', connectMode);
        connectButton.classList.toggle('btn-secondary', !connectMode);
        connectButton.textContent = connectMode ? 'Mode Hubungkan: ON' : 'Mode Hubungkan';
        hint.textContent = connectMode
            ? 'Klik task sumber, lalu task tujuan untuk membuat dependency Finish → Start.'
            : 'Drag node untuk mengatur layout. Mode Hubungkan: klik source lalu target.';
        clearSelection();
    });

    nodes.forEach((node) => {
        node.addEventListener('click', (event) => {
            if (!connectMode) return;
            const id = node.dataset.id;
            if (!connectSource) {
                connectSource = id;
                node.querySelector('rect')?.setAttribute('stroke', '#0a2540');
                hint.textContent = 'Sumber dipilih. Klik task tujuan.';
                return;
            }
            if (connectSource === id) {
                clearSelection();
                hint.textContent = 'Pilihan dibatalkan. Klik task sumber lagi.';
                return;
            }
            if (!confirm('Buat dependency: task tujuan menunggu task sumber?')) {
                clearSelection();
                return;
            }

            createDependency(connectSource, id);
        });
    });

    document.getElementById('resetGraph').addEventListener('click', () => {
        localStorage.removeItem(key);
        window.location.reload();
    });
})();
</script>
@endpush
