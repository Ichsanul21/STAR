<h2>Progress per Fase</h2>
<table class="data">
    <thead><tr><th>Fase</th><th>Bobot</th><th>Progress</th><th>Status</th></tr></thead>
    <tbody>
        @foreach ($event->phases as $phase)
            <tr><td>{{ $phase->name }}</td><td>{{ (float) $phase->weight }}</td><td>{{ (float) $phase->progress }}%</td><td>{{ $phase->status }}</td></tr>
        @endforeach
    </tbody>
</table>
