<div class="mb-3">
    <h2 class="text-xl font-extrabold">Log</h2>
    <p class="text-sm" style="color: var(--muted)">Log aplikasi, failed jobs, dan daftar route terdaftar.</p>
</div>

<div class="grid gap-4 xl:grid-cols-2">
    <div class="card">
        <div class="border-b px-5 py-4" style="border-color: var(--border)">
            <div class="section-title">Failed jobs</div>
        </div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Queue</th><th>Failed at</th></tr></thead>
                <tbody>
                    @forelse ($failedJobs as $job)
                        <tr>
                            <td class="text-xs">{{ $job->queue }}</td>
                            <td class="text-xs">{{ $job->failed_at }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="2" class="text-center text-sm" style="color: var(--muted)">Tidak ada failed job.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="border-b px-5 py-4" style="border-color: var(--border)">
            <div class="section-title">Log terakhir</div>
        </div>
        <pre class="max-h-80 overflow-auto p-4 text-[11px]">{{ $logTail }}</pre>
    </div>
</div>

<div class="card mt-4">
    <div class="border-b px-5 py-4" style="border-color: var(--border)">
        <div class="section-title">Routes</div>
        <div class="text-xs" style="color: var(--muted)">{{ $routes->count() }} route terdaftar</div>
    </div>
    <div class="max-h-96 overflow-auto">
        <table class="table">
            <thead><tr><th>Method</th><th>URI</th><th>Name</th><th>Middleware</th></tr></thead>
            <tbody>
                @foreach ($routes as $route)
                    <tr>
                        <td class="whitespace-nowrap text-[11px] font-mono">{{ $route['method'] }}</td>
                        <td class="text-[11px] font-mono">{{ $route['uri'] }}</td>
                        <td class="text-[11px]">{{ $route['name'] }}</td>
                        <td class="max-w-md truncate text-[10px]" style="color: var(--muted)" title="{{ $route['middleware'] }}">{{ $route['middleware'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
