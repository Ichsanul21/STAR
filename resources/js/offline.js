const DB_NAME = 'star-offline-forms';
const STORE = 'queue';

const queueRoot = document.querySelector('[data-offline-page]');
const listRoot = document.querySelector('[data-offline-list]');
const summaryRoot = document.querySelector('[data-offline-summary]');
const emptyRoot = document.querySelector('[data-offline-empty]');
const mergeDialog = document.getElementById('offlineMergeModal');
const mergeBody = document.getElementById('offlineMergeBody');
const mergeTitle = document.getElementById('offlineMergeTitle');
const mergeSave = document.getElementById('offlineMergeSave');

if (queueRoot && listRoot) {
    let mergeTarget = null;

    const openDb = () => new Promise((resolve, reject) => {
        const request = indexedDB.open(DB_NAME, 1);
        request.onupgradeneeded = () => request.result.createObjectStore(STORE, { keyPath: 'id' });
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });

    const withStore = async (mode, callback) => {
        const db = await openDb();

        return new Promise((resolve, reject) => {
            const tx = db.transaction(STORE, mode);
            const store = tx.objectStore(STORE);
            const result = callback(store);
            tx.oncomplete = () => resolve(result && 'result' in result ? result.result : result);
            tx.onerror = () => reject(tx.error);
        });
    };

    const all = () => withStore('readonly', (store) => store.getAll());
    const put = (entry) => withStore('readwrite', (store) => store.put(entry));
    const remove = (id) => withStore('readwrite', (store) => store.delete(id));

    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    }[char]));

    const formatTime = (timestamp) => new Date(timestamp).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' });

    const showToast = (message, type = 'success') => {
        const container = document.querySelector('[data-offline-toast]');
        if (!container) return;
        const toast = document.createElement('div');
        toast.className = `mb-3 flex items-start gap-3 rounded-xl border px-4 py-3 text-sm ${
            type === 'error' ? 'border-red-200 bg-red-50 text-red-800' : 'border-green-200 bg-green-50 text-green-800'
        }`;
        toast.textContent = message;
        container.prepend(toast);
        setTimeout(() => toast.remove(), 6000);
    };

    const endpointType = (action) => {
        const expense = action.match(/\/expenses\/(\d+)\/?$/);
        if (expense) return { type: 'expense', id: expense[1] };
        const task = action.match(/\/tasks\/(\d+)\/?$/);
        if (task) return { type: 'task', id: task[1] };
        const checklist = action.match(/\/checklists\/(\d+)\/complete\/?$/);
        if (checklist) return { type: 'checklist', id: checklist[1] };
        const event = action.match(/\/events\/(\d+)\/?$/);
        if (event) return { type: 'event', id: event[1] };
        return null;
    };

    const buildFormData = (entry) => {
        const formData = new FormData();
        Object.entries(entry.fields).forEach(([key, value]) => formData.append(key, value ?? ''));
        (entry.files || []).forEach((file) => formData.append(file.field, file.blob, file.name));

        return formData;
    };

    const sendEntry = async (entry) => {
        const response = await fetch(entry.action, {
            method: 'POST',
            body: buildFormData(entry),
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf(),
            },
        });

        if (response.redirected && response.url.includes('/login')) {
            return { ok: false, message: 'Sesi berakhir. Login ulang lalu coba lagi.', keep: true };
        }

        if (response.ok) {
            await remove(entry.id);

            return { ok: true };
        }

        let message = 'Server menolak data.';
        let json = null;
        try {
            json = await response.json();
            message = Object.values(json.errors ?? {}).flat().join(' ') || json.message || message;
        } catch (error) {
            // Ignore parse errors.
        }

        const versionConflict = [409, 412, 423].includes(response.status)
            || (response.status === 422 && Boolean(json?.errors?.conflict));

        if (versionConflict) {
            await put({ ...entry, conflict: message, conflict_id: json?.conflict_id || entry.conflict_id });

            return { ok: false, message, conflict: true };
        }

        if (response.status === 422) {
            await remove(entry.id);

            return { ok: false, message, dropped: true };
        }

        return { ok: false, message, keep: true };
    };

    const render = async () => {
        const items = (await all()).sort((a, b) => a.createdAt - b.createdAt);
        const conflicts = items.filter((item) => item.conflict);

        if (summaryRoot) {
            summaryRoot.textContent = `${items.length} antrean · ${conflicts.length} konflik`;
        }

        emptyRoot?.classList.toggle('hidden', items.length > 0);
        listRoot.innerHTML = '';

        items.forEach((entry) => {
            const mergeable = endpointType(entry.action);
            const card = document.createElement('div');
            card.className = 'card p-4';
            card.innerHTML = `
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="badge badge-brand">${escapeHtml(entry.label || 'Form')}</span>
                            ${entry.conflict ? '<span class="badge badge-danger">Konflik</span>' : '<span class="badge badge-warning">Menunggu</span>'}
                        </div>
                        <div class="mt-1 truncate text-xs" style="color: var(--muted)" title="${escapeHtml(entry.action)}">${escapeHtml(entry.action)}</div>
                        <div class="mt-1 text-xs" style="color: var(--muted)">Dibuat ${escapeHtml(formatTime(entry.createdAt))} · ${(entry.files || []).length} lampiran</div>
                    </div>
                    <div class="flex flex-wrap gap-1">
                        <button type="button" class="btn btn-secondary btn-sm" data-offline-retry>Kirim Ulang</button>
                        ${mergeable ? '<button type="button" class="btn btn-ghost btn-sm" data-offline-merge>Gabung</button>' : ''}
                        <button type="button" class="btn btn-ghost btn-sm" data-offline-discard>Hapus</button>
                    </div>
                </div>
                ${entry.conflict ? `<div class="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">${escapeHtml(entry.conflict)}${entry.conflict_id ? ` <a class="link font-semibold" href="/conflicts/${entry.conflict_id}">Buka merge →</a>` : ''}</div>` : ''}
                <details class="mt-3">
                    <summary class="cursor-pointer text-xs font-semibold" style="color: var(--brand)">Lihat data</summary>
                    <dl class="mt-2 grid gap-1 text-xs sm:grid-cols-2">
                        ${Object.entries(entry.fields).filter(([key]) => !['_token', '_method'].includes(key)).map(([key, value]) => `
                            <div class="flex gap-2"><dt class="w-32 shrink-0 font-mono" style="color: var(--muted)">${escapeHtml(key)}</dt><dd class="min-w-0 break-words">${escapeHtml(value)}</dd></div>
                        `).join('')}
                    </dl>
                </details>
            `;

            card.querySelector('[data-offline-retry]')?.addEventListener('click', async (event) => {
                event.target.disabled = true;
                event.target.textContent = 'Mengirim…';
                const result = await sendEntry(entry);
                if (result.ok) {
                    showToast('Antrean berhasil dikirim.');
                } else {
                    showToast(result.message, 'error');
                }
                await render();
            });

            card.querySelector('[data-offline-discard]')?.addEventListener('click', async () => {
                if (!confirm('Hapus antrean ini? Data yang belum terkirim akan hilang.')) return;
                await remove(entry.id);
                await render();
                showToast('Antrean dihapus.');
            });

            card.querySelector('[data-offline-merge]')?.addEventListener('click', async () => {
                await openMerge(entry, mergeable);
            });

            listRoot.appendChild(card);
        });
    };

    const openMerge = async (entry, target) => {
        mergeTarget = { entry, server: null };
        if (mergeSave) mergeSave.disabled = false;

        if (mergeTitle) mergeTitle.textContent = `Gabung ${entry.label || 'data'} dengan versi server`;

        if (mergeBody) {
            mergeBody.innerHTML = '<div class="text-sm" style="color: var(--muted)">Memuat data server…</div>';
        }

        mergeDialog?.showModal();

        try {
            const response = await fetch(`/conflicts/current?type=${target.type}&id=${target.id}`, {
                headers: { 'Accept': 'application/json' },
            });

            if (!response.ok) {
                throw new Error(response.status === 403 ? 'Tidak punya akses ke data ini.' : 'Data server tidak ditemukan.');
            }

            const server = await response.json();
            mergeTarget.server = server;

            const rows = Object.entries(server.attributes).map(([key, value]) => {
                const queued = entry.fields[key];
                const differs = queued !== undefined && String(queued) !== String(value ?? '');

                return `
                    <tr class="${differs ? 'row-selected' : ''}">
                        <td class="font-mono text-xs">${escapeHtml(key)}</td>
                        <td class="max-w-xs break-words text-xs">${queued !== undefined ? escapeHtml(queued) : '<span style="color: var(--muted)">—</span>'}</td>
                        <td class="max-w-xs break-words text-xs">${escapeHtml(value) ?? '—'}</td>
                        <td>${differs ? '<span class="badge badge-warning">Berbeda</span>' : '<span class="badge badge-muted">Sama</span>'}</td>
                    </tr>
                `;
            }).join('');

            mergeBody.innerHTML = `
                <div class="mb-4 rounded-xl bg-slate-50 p-3 text-xs" style="color: var(--muted)">
                    Data server terakhir diubah ${escapeHtml(server.updated_at || '—')} (versi v${server.lock_version}).
                    Pilih cara menggabungkan sebelum mengirim ulang.
                </div>
                <div class="mb-4 flex flex-wrap gap-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-merge-use-version>Pakai versi server (v${server.lock_version})</button>
                    <button type="button" class="btn btn-ghost btn-sm" data-merge-use-server>Pakai semua nilai server</button>
                </div>
                <div class="overflow-x-auto rounded-xl border" style="border-color: var(--border)">
                    <table class="table">
                        <thead><tr><th>Field</th><th>Antrean</th><th>Server</th><th>Status</th></tr></thead>
                        <tbody>${rows || '<tr><td colspan="4" class="text-center text-sm" style="color: var(--muted)">Tidak ada field yang bisa dibandingkan.</td></tr>'}</tbody>
                    </table>
                </div>
            `;

            mergeBody.querySelector('[data-merge-use-version]')?.addEventListener('click', () => {
                entry.fields.lock_version = String(server.lock_version);
                mergeBody.querySelector('[data-merge-use-version]').textContent = `Versi server dipakai (v${server.lock_version})`;
            });

            mergeBody.querySelector('[data-merge-use-server]')?.addEventListener('click', () => {
                Object.entries(server.attributes).forEach(([key, value]) => {
                    if (key === 'id') return;
                    if (value !== null && value !== undefined) entry.fields[key] = String(value);
                });
                entry.fields.lock_version = String(server.lock_version);
                showToast('Nilai antrean disamakan dengan server. Periksa kembali sebelum mengirim.');
            });
        } catch (error) {
            mergeBody.innerHTML = `<div class="rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">${escapeHtml(error.message)}</div>`;
            mergeSave.disabled = true;
        }
    };

    mergeSave?.addEventListener('click', async () => {
        if (!mergeTarget) return;

        await put(mergeTarget.entry);
        mergeDialog?.close();
        mergeSave.disabled = false;
        await render();
        showToast('Perubahan disimpan ke antrean. Gunakan "Kirim Ulang" untuk mengirim.');
    });

    document.querySelector('[data-offline-sync-all]')?.addEventListener('click', async (event) => {
        event.target.disabled = true;
        const items = await all();
        let sent = 0;

        for (const entry of items) {
            if (entry.conflict) continue;
            const result = await sendEntry(entry);
            if (result.ok) sent++;
        }

        event.target.disabled = false;
        showToast(`${sent} antrean terkirim.`);
        await render();
    });

    render();

    window.addEventListener('online', render);
}
