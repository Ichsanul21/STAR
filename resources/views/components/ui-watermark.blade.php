@props(['line1', 'line2', 'opacity' => 0.065, 'color' => '#0a2540', 'speed' => 70])

<div class="app-watermark" aria-hidden="true" style="--watermark-opacity: {{ $opacity }}; --watermark-color: {{ $color }};">
    @for ($band = 0; $band < 7; $band++)
        <div class="app-watermark-band" style="--band: {{ $band }}; animation-delay: -{{ $band * 6 }}s; animation-duration: {{ $speed }}s">
            @for ($copy = 0; $copy < 6; $copy++)
                <span class="app-watermark-item">
                    <span class="app-watermark-line1">{{ $line1 }}</span>
                    <span class="app-watermark-line2">{{ $line2 }}</span>
                </span>
            @endfor
        </div>
    @endfor
</div>
