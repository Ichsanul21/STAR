<div class="mb-3">
    <h2 class="text-xl font-extrabold">Watermark</h2>
    <p class="text-sm" style="color: var(--muted)">Watermark keamanan diagonal untuk halaman aplikasi dan PDF report.</p>
</div>

<div class="card card-pad">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="flex items-start gap-2">
            <span class="dev-console-mark" style="width:30px;height:30px;border-radius:9px"><x-icon name="documents" class="w-4 h-4" /></span>
            <div>
                <div class="section-title">Watermark keamanan</div>
                <div class="mt-1 text-xs" style="color: var(--muted)">Pita diagonal berjalan di seluruh halaman dan PDF. Baris kedua memuat identitas pengunjung.</div>
            </div>
        </div>
        <span class="badge badge-brand">{{ $watermarkSettings['enabled'] ? 'Aktif' : 'Nonaktif' }}</span>
    </div>

    <form id="watermarkForm" method="POST" action="{{ route('dev.watermark') }}" class="mt-4 space-y-4">
        @csrf
        <input type="hidden" name="enabled" value="0">
        <input type="hidden" name="login_enabled" value="0">

        <div class="grid gap-3 sm:grid-cols-2">
            <label class="flex items-center gap-2 rounded-xl border px-3 py-2 text-sm" style="border-color: var(--border)">
                <input type="checkbox" name="enabled" value="1" @checked($watermarkSettings['enabled'])>
                <span>Aktifkan watermark</span>
            </label>
            <label class="flex items-center gap-2 rounded-xl border px-3 py-2 text-sm" style="border-color: var(--border)">
                <input type="checkbox" name="login_enabled" value="1" @checked($watermarkSettings['login_enabled'])>
                <span>Tampil di halaman login</span>
            </label>
        </div>

        <div class="grid gap-3 lg:grid-cols-2">
            <div>
                <label class="label" for="wmLine1">Baris 1</label>
                <input id="wmLine1" name="line1" class="input" maxlength="160" required value="{{ old('line1', $watermarkSettings['line1']) }}">
            </div>
            <div>
                <label class="label" for="wmLine2">Baris 2</label>
                <input id="wmLine2" name="line2" class="input" maxlength="200" required value="{{ old('line2', $watermarkSettings['line2']) }}">
            </div>
            <div>
                <label class="label" for="wmOpacity">Opacity (0.01 – 0.50)</label>
                <input id="wmOpacity" name="opacity" type="number" step="0.005" min="0.01" max="0.5" class="input" required value="{{ old('opacity', $watermarkSettings['opacity']) }}">
            </div>
            <div>
                <label class="label" for="wmColor">Warna</label>
                <input id="wmColor" name="color" type="color" class="input h-11" required value="{{ old('color', $watermarkSettings['color']) }}">
            </div>
            <div>
                <label class="label" for="wmSpeed">Speed / durasi loop (detik)</label>
                <input id="wmSpeed" name="speed" type="number" min="10" max="300" class="input" required value="{{ old('speed', $watermarkSettings['speed']) }}">
            </div>
            <div>
                <label class="label" for="wmFont">Ukuran font (px)</label>
                <input id="wmFont" name="font_size" type="number" min="8" max="24" class="input" required value="{{ old('font_size', $watermarkSettings['font_size']) }}">
            </div>
        </div>

        <div class="rounded-xl px-3 py-2 text-[11px]" style="background: var(--brand-soft); color: var(--muted)">
            Token: <span class="font-mono">{ip}</span>, <span class="font-mono">{device}</span>,
            <span class="font-mono">{timestamp}</span>, <span class="font-mono">{user}</span>.
        </div>

        <div class="rounded-xl border p-3" style="border-color: var(--border)">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="text-[10px] font-bold uppercase tracking-wide" style="color: var(--muted)">Preset tampilan</div>
                <div class="flex flex-wrap gap-1.5">
                    <button type="button" class="btn btn-secondary btn-sm" data-wm-preset="soft">Halus</button>
                    <button type="button" class="btn btn-secondary btn-sm" data-wm-preset="standard">Standar</button>
                    <button type="button" class="btn btn-secondary btn-sm" data-wm-preset="strong">Tegas</button>
                </div>
            </div>
            <div class="mt-1 text-[11px]" style="color: var(--muted)">Preset mengubah opacity, kecepatan, dan ukuran font sekaligus.</div>

            <div id="wmPreview" class="mt-3 rounded-lg px-3 py-2" style="background: #fbfcfe; color: {{ $watermarkPreview['color'] }}; opacity: {{ $watermarkPreview['opacity'] }}; font-size: {{ $watermarkSettings['font_size'] }}px">
                <div class="font-extrabold" id="wmPreviewLine1">{{ $watermarkPreview['line1'] }}</div>
                <div id="wmPreviewLine2" style="font-size: 0.82em">{{ $watermarkPreview['line2'] }}</div>
            </div>
        </div>

        <button class="btn btn-primary btn-sm" type="submit">Simpan Watermark</button>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('watermarkForm');
    if (!form) return;

    const fields = {
        opacity: document.getElementById('wmOpacity'),
        speed: document.getElementById('wmSpeed'),
        font: document.getElementById('wmFont'),
        color: document.getElementById('wmColor'),
        line1: document.getElementById('wmLine1'),
        line2: document.getElementById('wmLine2'),
    };
    const preview = document.getElementById('wmPreview');
    const previewLine1 = document.getElementById('wmPreviewLine1');
    const previewLine2 = document.getElementById('wmPreviewLine2');

    const presets = {
        soft: { opacity: '0.040', speed: '120', font: '10' },
        standard: { opacity: '0.065', speed: '70', font: '12' },
        strong: { opacity: '0.120', speed: '35', font: '16' },
    };

    const syncPreview = () => {
        if (!preview) return;
        preview.style.color = fields.color.value;
        preview.style.opacity = fields.opacity.value;
        preview.style.fontSize = `${fields.font.value}px`;
        previewLine1.textContent = fields.line1.value;
        previewLine2.textContent = fields.line2.value;
    };

    document.querySelectorAll('[data-wm-preset]').forEach((button) => {
        button.addEventListener('click', () => {
            const preset = presets[button.dataset.wmPreset];
            if (!preset) return;
            fields.opacity.value = preset.opacity;
            fields.speed.value = preset.speed;
            fields.font.value = preset.font;
            syncPreview();
        });
    });

    Object.values(fields).forEach((field) => {
        field.addEventListener('input', syncPreview);
        field.addEventListener('change', syncPreview);
    });
});
</script>
