@php
    $photos = $event->media->sortByDesc('captured_at');
@endphp

<section id="documentation" class="scroll-mt-24">
    <div class="mb-3 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-xl font-extrabold">Documentation</h2>
            <p class="text-sm" style="color: var(--muted)">Foto wajib GPS + timestamp. Watermark dan map dibuat server-side.</p>
        </div>
        <div class="flex items-center gap-2 text-xs">
            <span class="badge badge-brand">Mode: {{ $event->capture_mode === 'camera_only' ? 'Camera only' : 'Upload / Camera' }}</span>
            <span id="queueBadge" class="badge badge-warning hidden">0 antrean</span>
        </div>
    </div>

    <div class="grid gap-4 xl:grid-cols-3">
        @if (auth()->user()->canWorkEvent($event))
        <details class="card xl:col-span-1">
            <summary class="cursor-pointer px-5 py-4 text-sm font-bold" style="color: var(--brand)">+ Upload / Ambil Foto</summary>
            <div class="card-pad border-t" style="border-color: var(--border)">
            <div class="section-title">Ambil / Upload Foto</div>
            <p class="mt-1 text-xs" style="color: var(--muted)">
                @if ($event->capture_mode === 'camera_only')
                    Mode camera only: foto harus diambil lewat kamera aplikasi.
                @else
                    Pilih foto dari galeri atau ambil lewat kamera.
                @endif
                GPS dan waktu wajib. Foto masuk antrean offline jika koneksi terputus.
            </p>

            <form id="photoForm" method="POST" action="{{ route('media.store', $event) }}" enctype="multipart/form-data" class="mt-4 space-y-3">
                @csrf
                <div>
                    <label class="label">Foto</label>
                    <input id="photoInput" type="file" name="photo" accept="image/jpeg,image/png" @if($event->capture_mode === 'camera_only') class="hidden" @else class="input" @endif>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <button id="openCamera" type="button" class="btn btn-secondary btn-sm">Buka Kamera</button>
                        <span id="photoChosen" class="self-center text-xs" style="color: var(--muted)">Belum ada foto dipilih.</span>
                    </div>
                    <img id="photoPreview" alt="" class="mt-3 hidden max-h-48 w-full rounded-xl object-cover">
                </div>
                <div>
                    <label class="label">Fase</label>
                    <select id="photoPhase" name="event_phase_id" class="input">
                        <option value="">Tanpa fase</option>
                        @foreach ($event->phases as $phase)
                            <option value="{{ $phase->id }}">{{ $phase->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label">Caption</label>
                    <input id="photoCaption" name="caption" class="input" placeholder="Auto-fill dari fase dan event">
                </div>
                <input type="hidden" name="captured_at" id="capturedAt">
                <input type="hidden" name="gps_lat" id="gpsLat">
                <input type="hidden" name="gps_lng" id="gpsLng">
                <input type="hidden" name="gps_accuracy" id="gpsAccuracy">
                <div id="gpsStatus" class="rounded-xl bg-slate-50 px-3 py-2 text-xs" style="color: var(--muted)">Mengambil lokasi GPS…</div>
                <button class="btn btn-primary w-full" type="submit">Simpan Foto</button>
            </form>

            <div id="cameraModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/90 p-4">
                <div class="w-full max-w-3xl overflow-hidden rounded-2xl bg-black">
                    <div class="flex items-center justify-between px-4 py-3 text-white">
                        <div class="text-sm font-bold">Kamera STAR</div>
                        <button id="closeCamera" type="button" class="rounded-lg px-3 py-1 text-sm text-slate-300 hover:bg-white/10">Tutup</button>
                    </div>
                    <video id="cameraVideo" autoplay playsinline class="max-h-[70vh] w-full object-contain"></video>
                    <div class="flex items-center justify-center gap-3 p-4">
                        <button id="shutter" type="button" class="rounded-full bg-white px-6 py-3 text-sm font-bold text-slate-900">Ambil Foto</button>
                    </div>
                </div>
            </div>
            </div>
        </details>
        @endif

        <div class="card {{ auth()->user()->canWorkEvent($event) ? 'xl:col-span-2' : 'xl:col-span-3' }}">
            <div class="border-b px-5 py-4" style="border-color: var(--border)">
                <div class="section-title">Galeri</div>
                <div class="text-xs" style="color: var(--muted)">{{ $photos->count() }} foto · watermarked</div>
            </div>
            <div class="grid grid-cols-2 gap-3 p-5 sm:grid-cols-3 lg:grid-cols-4">
                @forelse ($photos as $media)
                    <div class="group">
                        <a href="{{ route('media.file', $media) }}" data-preview="image" data-preview-title="{{ $media->caption ?: 'Dokumentasi' }}" data-preview-meta="{{ json_encode(\App\Support\PreviewMeta::media($media)) }}" class="block aspect-square overflow-hidden rounded-xl bg-slate-100">
                            <img src="{{ route('media.file', $media) }}" alt="{{ $media->caption }}" class="h-full w-full object-cover transition group-hover:scale-105">
                        </a>
                        <div class="mt-1 flex items-center justify-between gap-1">
                            <div class="min-w-0">
                                <div class="truncate text-[10px] font-semibold">{{ $media->caption ?: 'Tanpa caption' }}</div>
                                <div class="truncate text-[9px]" style="color: var(--muted)">{{ $media->phase?->name }} · {{ $media->captured_at?->format('d/m H:i') }}</div>
                            </div>
                            <x-status :status="$media->status" />
                        </div>
                        @if (auth()->user()->canReviewEvent($event))
                            <div class="mt-1 flex gap-1">
                                <form method="POST" action="{{ route('media.approve', $media) }}">@csrf<button class="btn btn-ghost btn-sm !px-1.5 !py-1 text-[10px]">✓</button></form>
                                <form method="POST" action="{{ route('media.reject', $media) }}">@csrf<button class="btn btn-ghost btn-sm !px-1.5 !py-1 text-[10px]">✕</button></form>
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="col-span-full"><x-empty title="Belum ada foto" body="Upload foto pertama; watermark GPS akan dibuat otomatis." /></div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="mt-4 grid gap-4 xl:grid-cols-2">
        <div class="card card-pad">
            <div class="section-title">Visitor / Lead</div>
            @if (auth()->user()->canWorkEvent($event))
            <form method="POST" action="{{ route('visitors.store', $event) }}" class="mt-3 grid gap-3 sm:grid-cols-2" data-offline-form data-offline-label="Visitor">
                @csrf
                <div><label class="label">Nama</label><input name="name" class="input" required></div>
                <div><label class="label">No. HP</label><input name="phone" class="input" required></div>
                <div><label class="label">Kota</label><input name="city" class="input" data-autocomplete data-remote="{{ route('lookup.cities') }}" data-creatable></div>
                <div><label class="label">Sumber</label><select name="source" class="input"><option value="walk_in">Walk-in</option><option value="invitation">Undangan</option><option value="social_media">Social Media</option><option value="referral">Referral</option><option value="dealer_database">Database Dealer</option></select></div>
                <div><label class="label">Minat Model</label><input name="interest_model" class="input" data-autocomplete data-remote="{{ route('lookup.models') }}" data-creatable></div>
                <div><label class="label">Status Lead</label><select name="lead_status" class="input"><option value="new">New</option><option value="follow_up">Follow Up</option><option value="hot">Hot</option><option value="warm">Warm</option><option value="cold">Cold</option><option value="closing">Closing</option><option value="lost">Lost</option></select></div>
                <div class="sm:col-span-2"><label class="flex items-center gap-2 text-xs"><input type="checkbox" name="consent" value="1" required> Saya setuju data diproses untuk follow-up.</label></div>
                <div class="sm:col-span-2"><button class="btn btn-primary btn-sm" type="submit">Simpan Pengunjung</button></div>
            </form>
            @endif
            <div class="mt-4 space-y-1 text-xs">
                @foreach ($event->visitors->take(5) as $visitor)
                    <div class="flex justify-between rounded-lg bg-slate-50 px-3 py-2"><span>{{ $visitor->name }}</span><span style="color: var(--muted)">{{ $visitor->interest_model ?: '—' }}</span></div>
                @endforeach
            </div>
        </div>

        <div class="card card-pad">
            <div class="section-title">Attachment Dokumen</div>
            <p class="mt-1 text-xs" style="color: var(--muted)">PDF, DOCX, XLSX, JPG, JPEG, PNG.</p>
            @if (auth()->user()->canWorkEvent($event))
            <form method="POST" action="{{ route('attachments.store', $event) }}" enctype="multipart/form-data" class="mt-3 grid gap-3 sm:grid-cols-2">
                @csrf
                <div><label class="label">Kategori</label><select name="category" class="input"><option value="rab">RAB</option><option value="planning">Planning</option><option value="rundown">Rundown</option><option value="permit">Perizinan</option><option value="vendor">Vendor</option><option value="other">Lainnya</option></select></div>
                <div><label class="label">File</label><input type="file" name="document" class="input" required accept=".pdf,.docx,.xlsx,.jpg,.jpeg,.png"></div>
                <div class="sm:col-span-2"><button class="btn btn-secondary btn-sm" type="submit">Upload Lampiran</button></div>
            </form>
            @endif
            <div class="mt-4 space-y-1 text-xs">
                @forelse ($event->attachments as $attachment)
                    @php
                        $previewType = str_starts_with($attachment->mime, 'image/') ? 'image' : ($attachment->mime === 'application/pdf' ? 'pdf' : 'download');
                    @endphp
                    <a href="{{ route('attachments.show', $attachment) }}" data-preview="{{ $previewType }}" data-preview-title="{{ $attachment->original_name }}" data-preview-meta="{{ json_encode(\App\Support\PreviewMeta::attachment($attachment)) }}" data-download-url="{{ route('attachments.show', $attachment) }}" class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2 hover:bg-slate-100">
                        <span class="truncate">{{ $attachment->original_name }}</span>
                        <span class="badge badge-muted">{{ $attachment->category }}</span>
                    </a>
                @empty
                    <div style="color: var(--muted)">Belum ada lampiran.</div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="card card-pad mt-4">
        <div class="section-title">Hasil Event</div>
        <p class="mt-1 text-xs" style="color: var(--muted)">Hasil manual; visitor/lead/test drive otomatis dari data pengunjung.</p>
        @if (auth()->user()->canManageEvent($event))
        <form method="POST" action="{{ route('results.update', $event) }}" class="mt-3 grid gap-3 sm:grid-cols-3 xl:grid-cols-6">
            @csrf @method('PATCH')
            @foreach (['visitors' => 'Pengunjung', 'leads' => 'Leads', 'test_drives' => 'Test Drive', 'spk' => 'SPK', 'units_sold' => 'Unit', 'handover' => 'Handover'] as $field => $label)
                <div><label class="label">{{ $label }}</label><input type="number" min="0" name="{{ $field }}" value="{{ $event->result?->{$field} ?? 0 }}" class="input"></div>
            @endforeach
            <div class="sm:col-span-2"><label class="label">Revenue</label><input type="number" step="0.01" min="0" name="revenue" value="{{ $event->result?->revenue ?? 0 }}" class="input"></div>
            <div class="sm:col-span-2"><label class="label">Catatan</label><input name="notes" value="{{ $event->result?->notes }}" class="input"></div>
            <div class="sm:col-span-3 xl:col-span-6"><button class="btn btn-primary btn-sm" type="submit">Simpan Hasil</button></div>
        </form>
        @endif
    </div>
</section>

@push('scripts')
<script>
(() => {
    const lat = document.getElementById('gpsLat');
    const lng = document.getElementById('gpsLng');
    const acc = document.getElementById('gpsAccuracy');
    const at = document.getElementById('capturedAt');
    const status = document.getElementById('gpsStatus');
    const form = document.getElementById('photoForm');
    const caption = document.getElementById('photoCaption');
    const phase = document.getElementById('photoPhase');
    const queueBadge = document.getElementById('queueBadge');
    const eventName = @json($event->name);
    const eventLocation = @json($event->location ?: $event->city ?: '');

    const nowLocal = () => new Date().toISOString();
    at.value = nowLocal();

    const autoCaption = () => {
        if (caption.value.trim() !== '') return;
        const phaseText = phase.options[phase.selectedIndex]?.text || 'Dokumentasi';
        caption.value = `${phaseText} - ${eventName} - ${eventLocation} - ${new Date().toLocaleString('id-ID')}`;
    };
    phase.addEventListener('change', autoCaption);
    autoCaption();

    navigator.geolocation?.getCurrentPosition((pos) => {
        lat.value = pos.coords.latitude.toFixed(7);
        lng.value = pos.coords.longitude.toFixed(7);
        acc.value = pos.coords.accuracy ? pos.coords.accuracy.toFixed(1) : '';
        status.textContent = `GPS aktif · ${lat.value}, ${lng.value} · akurasi ±${Math.round(pos.coords.accuracy || 0)} m`;
        status.className = 'rounded-xl bg-green-50 px-3 py-2 text-xs text-green-800';
    }, (err) => {
        status.textContent = 'GPS tidak tersedia: ' + err.message + '. Foto tidak bisa disimpan tanpa GPS.';
        status.className = 'rounded-xl bg-red-50 px-3 py-2 text-xs text-red-800';
    }, { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 });

    const photoInput = document.getElementById('photoInput');
    const photoPreview = document.getElementById('photoPreview');
    const photoChosen = document.getElementById('photoChosen');
    const cameraModal = document.getElementById('cameraModal');
    const cameraVideo = document.getElementById('cameraVideo');
    const openCameraBtn = document.getElementById('openCamera');
    let cameraStream = null;

    const showPhoto = (file) => {
        if (!file) return;
        photoChosen.textContent = `${file.name} · ${Math.round(file.size / 1024)} KB`;
        photoPreview.src = URL.createObjectURL(file);
        photoPreview.classList.remove('hidden');
    };

    photoInput.addEventListener('change', () => showPhoto(photoInput.files[0]));

    const closeCamera = () => {
        cameraStream?.getTracks().forEach((track) => track.stop());
        cameraStream = null;
        cameraVideo.srcObject = null;
        cameraModal.classList.add('hidden');
        cameraModal.classList.remove('flex');
    };

    openCameraBtn.addEventListener('click', async () => {
        if (!navigator.mediaDevices?.getUserMedia) {
            alert('Browser ini tidak mendukung kamera in-app. Gunakan browser modern atau mode upload.');
            return;
        }
        try {
            cameraStream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: { ideal: 'environment' } },
                audio: false
            });
            cameraVideo.srcObject = cameraStream;
            cameraModal.classList.remove('hidden');
            cameraModal.classList.add('flex');
        } catch (error) {
            alert('Kamera tidak bisa dibuka: ' + error.message);
        }
    });

    document.getElementById('closeCamera').addEventListener('click', closeCamera);

    document.getElementById('shutter').addEventListener('click', () => {
        if (!cameraVideo.videoWidth) return;
        const canvas = document.createElement('canvas');
        canvas.width = cameraVideo.videoWidth;
        canvas.height = cameraVideo.videoHeight;
        canvas.getContext('2d').drawImage(cameraVideo, 0, 0, canvas.width, canvas.height);
        canvas.toBlob((blob) => {
            if (!blob) return;
            const file = new File([blob], `camera-${Date.now()}.jpg`, { type: 'image/jpeg' });
            const transfer = new DataTransfer();
            transfer.items.add(file);
            photoInput.files = transfer.files;
            showPhoto(file);
            closeCamera();
        }, 'image/jpeg', 0.9);
    });

    const dbName = 'star-media-queue';
    const storeName = 'items';
    const openDb = () => new Promise((resolve, reject) => {
        const req = indexedDB.open(dbName, 1);
        req.onupgradeneeded = () => req.result.createObjectStore(storeName, { keyPath: 'id' });
        req.onsuccess = () => resolve(req.result);
        req.onerror = () => reject(req.error);
    });

    const withStore = async (mode, fn) => {
        const db = await openDb();
        return new Promise((resolve, reject) => {
            const tx = db.transaction(storeName, mode);
            const store = tx.objectStore(storeName);
            const result = fn(store);
            tx.oncomplete = () => resolve(result && 'result' in result ? result.result : result);
            tx.onerror = () => reject(tx.error);
        });
    };

    const put = (entry) => withStore('readwrite', (store) => store.put(entry));
    const all = () => withStore('readonly', (store) => store.getAll());
    const del = (id) => withStore('readwrite', (store) => store.delete(id));

    const updateBadge = async () => {
        const items = await all();
        queueBadge.textContent = `${items.length} antrean`;
        queueBadge.classList.toggle('hidden', items.length === 0);
    };

    const syncQueue = async () => {
        if (!navigator.onLine) { await updateBadge(); return; }
        const items = await all();
        for (const entry of items) {
            const fd = new FormData();
            Object.entries(entry.fields).forEach(([k, v]) => fd.append(k, v));
            fd.append('photo', entry.photo, entry.filename || 'photo.jpg');
            try {
                const res = await fetch(entry.action, {
                    method: 'POST',
                    body: fd,
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
                });
                if (res.ok) await del(entry.id);
            } catch (e) {
                // Keep queued.
            }
        }
        await updateBadge();
    };

    form.addEventListener('submit', async (e) => {
        const file = document.getElementById('photoInput').files[0];
        if (!file) return;
        if (!lat.value || !lng.value) {
            e.preventDefault();
            alert('GPS belum tersedia. Aktifkan izin lokasi lalu coba lagi.');
            return;
        }
        e.preventDefault();
        const fd = new FormData(form);
        const fields = {};
        for (const [k, v] of fd.entries()) if (k !== 'photo') fields[k] = v;
        fields.captured_at = nowLocal();
        const entry = {
            id: crypto.randomUUID(),
            action: form.action,
            fields,
            photo: file,
            filename: file.name,
        };
        await put(entry);
        await updateBadge();
        form.reset();
        at.value = nowLocal();
        autoCaption();
        await syncQueue();
        if (navigator.onLine) window.location.reload();
    });

    window.addEventListener('online', syncQueue);
    updateBadge().then(syncQueue);
})();
</script>
@endpush
