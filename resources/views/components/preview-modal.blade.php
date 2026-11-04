<dialog id="previewModal" class="preview-modal">
    <div class="preview-modal-panel">
        <div class="preview-modal-head">
            <div class="min-w-0">
                <div class="text-sm font-bold" data-preview-title>Preview</div>
                <div class="truncate text-xs" data-preview-subtitle style="color: var(--muted)"></div>
            </div>
            <div class="flex items-center gap-2">
                <a href="#" class="btn btn-secondary btn-sm hidden" data-preview-download download>Download</a>
                <button type="button" class="btn btn-ghost btn-sm" data-preview-close aria-label="Tutup">✕</button>
            </div>
        </div>
        <div class="preview-modal-body">
            <div class="preview-modal-stage" data-preview-stage></div>
            <aside class="preview-modal-meta" data-preview-meta-panel hidden></aside>
        </div>
    </div>
</dialog>

@push('scripts')
<script>
(() => {
    const modal = document.getElementById('previewModal');
    if (!modal) return;

    const stage = modal.querySelector('[data-preview-stage]');
    const metaPanel = modal.querySelector('[data-preview-meta-panel]');
    const title = modal.querySelector('[data-preview-title]');
    const subtitle = modal.querySelector('[data-preview-subtitle]');
    const download = modal.querySelector('[data-preview-download]');
    const close = () => modal.close();

    const inferType = (url) => {
        if (/\/media\/\d+\/file/.test(url)) return 'image';
        if (/\/reports\/\d+\/preview/.test(url)) return 'html';
        if (/\/reports\/\d+\/pdf/.test(url) || url.toLowerCase().endsWith('.pdf')) return 'pdf';
        if (/\/attachments\/\d+/.test(url)) return 'auto';
        return null;
    };

    const renderMeta = (meta) => {
        metaPanel.innerHTML = '';
        const groups = meta?.groups ?? [];

        if (!groups.length) {
            metaPanel.hidden = true;

            return;
        }

        metaPanel.hidden = false;

        groups.forEach((group) => {
            const section = document.createElement('section');
            section.className = 'meta-group';

            const heading = document.createElement('div');
            heading.className = 'meta-title';
            heading.textContent = group.title;
            section.appendChild(heading);

            const list = document.createElement('dl');
            list.className = 'meta-list';

            (group.rows ?? []).forEach(([label, value]) => {
                const row = document.createElement('div');
                row.className = 'meta-row';
                const dt = document.createElement('dt');
                dt.textContent = label;
                const dd = document.createElement('dd');
                dd.textContent = value;
                row.append(dt, dd);
                list.appendChild(row);
            });

            section.appendChild(list);

            if (group.map && group.map.lat != null && group.map.lng != null) {
                const lat = Number(group.map.lat);
                const lng = Number(group.map.lng);
                const delta = 0.004;
                const bbox = [lng - delta, lat - delta, lng + delta, lat + delta].join('%2C');

                const map = document.createElement('iframe');
                map.className = 'preview-map';
                map.loading = 'lazy';
                map.title = 'Lokasi dokumentasi';
                map.src = `https://www.openstreetmap.org/export/embed.html?bbox=${bbox}&layer=mapnik&marker=${lat}%2C${lng}`;
                section.appendChild(map);

                const link = document.createElement('a');
                link.className = 'link text-[11px]';
                link.href = `https://www.openstreetmap.org/?mlat=${lat}&mlon=${lng}#map=17/${lat}/${lng}`;
                link.target = '_blank';
                link.rel = 'noopener';
                link.textContent = 'Buka peta di OpenStreetMap';
                section.appendChild(link);

                const attribution = document.createElement('div');
                attribution.className = 'mt-1 text-[10px]';
                attribution.style.color = 'var(--muted)';
                attribution.textContent = '© OpenStreetMap contributors';
                section.appendChild(attribution);
            }

            metaPanel.appendChild(section);
        });
    };

    const open = (url, type, label, downloadUrl, meta) => {
        stage.innerHTML = '';
        title.textContent = label || 'Preview';
        subtitle.textContent = meta?.subtitle || url;
        download.classList.toggle('hidden', !downloadUrl);
        if (downloadUrl) download.href = downloadUrl;

        if (type === 'image') {
            const img = document.createElement('img');
            img.src = url;
            img.alt = label || 'Preview';
            img.className = 'preview-modal-image';
            stage.appendChild(img);
        } else if (type === 'download') {
            const box = document.createElement('div');
            box.className = 'preview-modal-message';
            box.textContent = 'Preview tidak tersedia untuk tipe file ini. Gunakan tombol Download.';
            stage.appendChild(box);
        } else {
            const iframe = document.createElement('iframe');
            iframe.src = url;
            iframe.className = 'preview-modal-frame';
            iframe.loading = 'lazy';
            stage.appendChild(iframe);
        }

        renderMeta(meta);
        modal.showModal();
    };

    document.addEventListener('click', (event) => {
        const link = event.target.closest('a[target="_blank"], a[data-preview]');
        if (!link) return;
        if (link.hasAttribute('data-preview-download')) return;
        if (!link.href || link.href.startsWith('#')) return;

        const type = link.dataset.preview || inferType(link.href);
        if (!type || type === 'skip') return;

        event.preventDefault();
        const label = link.dataset.previewTitle || link.textContent.trim() || 'Preview';
        let meta = null;

        if (link.dataset.previewMeta) {
            try {
                meta = JSON.parse(link.dataset.previewMeta);
            } catch (error) {
                meta = null;
            }
        }

        open(link.href, type, label, link.dataset.downloadUrl || link.href, meta);
    });

    modal.querySelector('[data-preview-close]')?.addEventListener('click', close);
    modal.addEventListener('click', (event) => {
        if (event.target === modal) close();
    });
    modal.addEventListener('close', () => {
        stage.innerHTML = '';
        metaPanel.innerHTML = '';
    });
})();
</script>
@endpush
