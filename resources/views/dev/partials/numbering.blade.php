<div class="mb-3">
    <h2 class="text-xl font-extrabold">Nomor</h2>
    <p class="text-sm" style="color: var(--muted)">Format penomoran otomatis event, invoice, SPK, dan report.</p>
</div>

<div class="card card-pad">
    <form method="POST" action="{{ route('global.settings.numbering') }}" class="space-y-4">
        @csrf
        @method('PATCH')

        <div class="grid gap-3 lg:grid-cols-2">
            @foreach (\App\Services\NumberingService::LABELS as $type => $label)
                <div class="rounded-xl border p-3" style="border-color: var(--border)">
                    <label class="label" for="dev-numbering-{{ $type }}">{{ $label }}</label>
                    <input
                        id="dev-numbering-{{ $type }}"
                        name="{{ $type }}_format"
                        value="{{ old($type.'_format', $numberingFormats[$type]) }}"
                        class="input font-mono text-xs"
                        maxlength="80"
                        required
                    >
                    @error($type.'_format')
                        <div class="mt-1 text-xs" style="color: var(--danger)">{{ $message }}</div>
                    @enderror
                    <div class="mt-1 text-[11px]" style="color: var(--muted)">
                        Contoh: <span class="font-mono">{{ $numberingPreviews[$type] }}</span>
                    </div>
                    <button
                        type="submit"
                        name="reset"
                        value="{{ $type }}"
                        class="link mt-2 text-[11px]"
                        formnovalidate
                        onclick="return confirm('Kembalikan format {{ $label }} ke default?')"
                    >Kembalikan default</button>
                </div>
            @endforeach
        </div>

        <div class="rounded-xl px-3 py-2 text-[11px]" style="background: var(--brand-soft); color: var(--muted)">
            Token: <span class="font-mono">{prefix}</span>, <span class="font-mono">{YYYY}</span>,
            <span class="font-mono">{YY}</span>, <span class="font-mono">{MM}</span>,
            <span class="font-mono">{DD}</span>, <span class="font-mono">{seq}</span>,
            <span class="font-mono">{seq:4}</span>, <span class="font-mono">{event}</span>,
            <span class="font-mono">{dealer}</span>, <span class="font-mono">{random:4}</span>.
            Format wajib mengandung <span class="font-mono">{seq}</span>.
        </div>

        <button class="btn btn-primary" type="submit">Simpan Format Penomoran</button>
    </form>
</div>
