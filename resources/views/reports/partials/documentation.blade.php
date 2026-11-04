<h2>Dokumentasi</h2>
@if ($photos->isEmpty())
    <div class="muted">Belum ada foto.</div>
@else
    <table class="photos">
        @foreach ($photos->chunk(2) as $chunk)
            <tr>
                @foreach ($chunk as $photo)
                    <td>
                        <img class="photo" src="{{ $photo['data'] }}" alt="">
                        <div class="caption">{{ $photo['caption'] ?: 'Tanpa caption' }} · {{ $photo['phase'] }} · {{ $photo['time'] }}</div>
                    </td>
                @endforeach
            </tr>
        @endforeach
    </table>
@endif
