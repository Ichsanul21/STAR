import './bootstrap';

if (document.querySelector('meta[name="user-id"]')) {
    import('./realtime');
}

if (document.querySelector('[data-rich-text]')) {
    import('./editor');
}

if (document.querySelector('[data-offline-page]')) {
    import('./offline');
}

if (document.querySelector('[data-dashboard-chart]')) {
    import('./charts');
}

if (document.querySelector('[data-scurve]')) {
    import('./scurve');
}

if (document.querySelector('select')) {
    import('./selects');
}

document.addEventListener('click', (event) => {
    const opener = event.target.closest('[data-modal-target]');
    if (opener) {
        event.preventDefault();
        const modal = document.getElementById(opener.dataset.modalTarget);
        if (modal) modal.showModal();
        return;
    }

    const closer = event.target.closest('[data-modal-close]');
    if (closer) {
        closer.closest('dialog')?.close();
    }
});

document.querySelectorAll('dialog[data-auto-open]').forEach((dialog) => dialog.showModal());

let pwaPrompt = null;

window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
    pwaPrompt = event;
    document.querySelectorAll('[data-pwa-install]').forEach((button) => button.removeAttribute('hidden'));
    document.querySelectorAll('[data-pwa-hint]').forEach((hint) => hint.classList.add('hidden'));
});

document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-pwa-install]');
    if (!button) return;

    if (!pwaPrompt) {
        alert('Gunakan menu browser lalu pilih "Add to Home Screen" / "Install app".');

        return;
    }

    pwaPrompt.prompt();
    await pwaPrompt.userChoice;
    pwaPrompt = null;
    button.setAttribute('hidden', 'hidden');
});

const routeProgress = document.createElement('div');
routeProgress.className = 'route-progress';
document.body.appendChild(routeProgress);

const startRouteProgress = () => routeProgress.classList.add('is-active');

document.addEventListener('submit', (event) => {
    setTimeout(() => {
        if (!event.defaultPrevented && event.target.target !== '_blank') startRouteProgress();
    }, 0);
});

document.addEventListener('click', (event) => {
    const link = event.target.closest('a[href]');
    if (!link || event.defaultPrevented) return;
    if (link.target === '_blank' || link.hasAttribute('download') || link.dataset.noProgress !== undefined) return;
    if (link.getAttribute('href').startsWith('#') || link.getAttribute('href').startsWith('javascript')) return;
    if (event.metaKey || event.ctrlKey || event.shiftKey || event.button !== 0) return;

    startRouteProgress();
});

const dismissToast = (toast) => {
    toast.style.opacity = '0';
    toast.style.transform = 'translateY(-4px)';
    toast.style.transition = 'opacity .2s ease, transform .2s ease';
    setTimeout(() => toast.remove(), 220);
};

document.querySelectorAll('[data-toast]').forEach((toast) => {
    toast.querySelector('[data-toast-close]')?.addEventListener('click', () => dismissToast(toast));
    setTimeout(() => { if (toast.isConnected) dismissToast(toast); }, 6000);
});

const bulkForm = document.querySelector('[data-bulk-form]');
if (bulkForm) {
    const master = document.querySelector('[data-bulk-all]');
    const items = [...document.querySelectorAll('[data-bulk-item]')];
    const counter = document.querySelector('[data-bulk-count]');
    const actionButton = document.querySelector('[data-bulk-apply]');

    const refreshBulk = () => {
        const selected = items.filter((item) => item.checked).length;
        if (counter) counter.textContent = selected;
        if (master) master.checked = selected > 0 && selected === items.length;
        items.forEach((item) => item.closest('tr')?.classList.toggle('row-selected', item.checked));
        if (actionButton) actionButton.disabled = selected === 0;
    };

    master?.addEventListener('change', () => {
        items.forEach((item) => { item.checked = master.checked; });
        refreshBulk();
    });

    items.forEach((item) => item.addEventListener('change', refreshBulk));
    refreshBulk();

    bulkForm.addEventListener('submit', (event) => {
        const selected = items.filter((item) => item.checked).length;
        if (selected === 0) {
            event.preventDefault();
            alert('Pilih minimal satu expense.');
        }
    });
}

const ganttModal = document.getElementById('ganttTaskModal');
const ganttForm = document.getElementById('ganttTaskForm');

if (ganttModal && ganttForm) {
    const fields = {
        name: document.getElementById('ganttTaskName'),
        description: document.getElementById('ganttTaskDescription'),
        start: document.getElementById('ganttTaskStart'),
        due: document.getElementById('ganttTaskDue'),
        priority: document.getElementById('ganttTaskPriority'),
        assignee: document.getElementById('ganttTaskAssignee'),
        lock: document.getElementById('ganttTaskLock'),
        updated: document.getElementById('ganttTaskUpdated'),
        status: document.getElementById('ganttTaskStatus'),
        open: document.getElementById('ganttTaskOpen'),
    };

    const statusLabels = {
        todo: 'Todo',
        in_progress: 'In Progress',
        blocked: 'Blocked',
        review: 'Review',
        done: 'Done',
        cancelled: 'Cancelled',
    };

    const setSelectValue = (element, value) => {
        if (!element) return;

        if (element.tomselect) {
            element.tomselect.setValue(value || '', true);

            return;
        }

        element.value = value || '';
    };

    document.querySelectorAll('[data-gantt-task]').forEach((row) => {
        row.addEventListener('click', () => {
            const data = row.dataset;

            fields.name.value = data.taskName || '';
            fields.description.value = data.taskDescription || '';
            fields.start.value = data.taskStart || '';
            fields.due.value = data.taskDue || '';
            setSelectValue(fields.priority, data.taskPriority || 'medium');
            setSelectValue(fields.assignee, data.taskAssignee || '');
            fields.lock.value = data.taskLock || '';
            fields.updated.value = data.taskUpdated || '';
            fields.status.textContent = statusLabels[data.taskStatus] || data.taskStatus || 'Task';
            fields.open.href = data.taskOpen || '#';

            ganttForm.action = data.taskUrl || '';
            ganttModal.showModal();
        });
    });
}

const filterTable = (tableId) => {
    const table = document.getElementById(tableId);
    if (!table) return;
    const search = (document.querySelector(`[data-table-filter="${tableId}"]`)?.value || '').toLowerCase();
    const status = document.querySelector(`[data-table-filter-status="${tableId}"]`)?.value || '';

    [...table.querySelectorAll('tbody tr[data-search]')].forEach((row) => {
        const matchesSearch = !search || (row.dataset.search || '').includes(search);
        const matchesStatus = !status || row.dataset.status === status;
        row.classList.toggle('hidden', !(matchesSearch && matchesStatus));
    });
};

document.querySelectorAll('[data-table-filter]').forEach((input) => {
    input.addEventListener('input', () => filterTable(input.dataset.tableFilter));
});
document.querySelectorAll('[data-table-filter-status]').forEach((select) => {
    select.addEventListener('change', () => filterTable(select.dataset.tableFilterStatus));
});

document.querySelectorAll('dialog.app-modal, dialog.preview-modal').forEach((dialog) => {
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) dialog.close();
    });
});

document.addEventListener('change', async (event) => {
    const expenseSelect = event.target.closest('[data-expense-event]');

    if (expenseSelect) {
        const form = expenseSelect.closest('form');
        const budgetSelect = form?.querySelector('[data-expense-budget-items]');
        if (!budgetSelect) return;

        const base = expenseSelect.dataset.budgetBase || '/events';
        const options = [{ value: '', text: 'Unbudgeted' }];

        if (expenseSelect.value) {
            try {
                const response = await fetch(`${base}/${expenseSelect.value}/budget-items`, { headers: { 'Accept': 'application/json' } });
                if (response.ok) {
                    const json = await response.json();
                    (json.items || []).forEach((item) => options.push({ value: String(item.value), text: item.text }));
                }
            } catch (error) {
                // Leave the empty option only.
            }
        }

        if (budgetSelect.tomselect) {
            budgetSelect.tomselect.clearOptions();
            options.forEach((option) => budgetSelect.tomselect.addOption(option));
            budgetSelect.tomselect.setValue('', true);
            budgetSelect.tomselect.refreshOptions(false);
        } else {
            budgetSelect.innerHTML = options.map((option) => `<option value="${option.value}">${option.text}</option>`).join('');
        }

        return;
    }

    const invoiceSelect = event.target.closest('[data-invoice-event]');

    if (invoiceSelect) {
        const form = invoiceSelect.closest('form');
        const base = invoiceSelect.dataset.financeBase || '/events';

        if (!invoiceSelect.value) return;

        try {
            const response = await fetch(`${base}/${invoiceSelect.value}/finance-context`, { headers: { 'Accept': 'application/json' } });
            if (!response.ok) return;
            const json = await response.json();

            const format = (value) => 'Rp' + Number(value || 0).toLocaleString('id-ID');
            const info = form.querySelector('[data-invoice-info]');
            const number = form.querySelector('[data-invoice-number]');
            const amount = form.querySelector('[data-invoice-amount]');

            if (info) {
                info.innerHTML = `Contract <b>${format(json.contract)}</b> · Invoiced <b>${format(json.invoiced)}</b> · Sisa <b>${format(json.remaining)}</b>`;
            }
            if (number && !number.value) number.value = json.suggested_number;
            if (amount) {
                amount.max = json.remaining;
                if (!amount.value || Number(amount.value) === 0) amount.value = json.remaining;
            }
        } catch (error) {
            // Ignore context errors.
        }

        return;
    }

    const paymentSelect = event.target.closest('[data-payment-invoice]');

    if (paymentSelect) {
        const form = paymentSelect.closest('form');
        const amount = form?.querySelector('[data-payment-amount]');
        const balance = paymentSelect.selectedOptions[0]?.dataset.balance;

        if (amount && balance) {
            amount.value = balance;
            amount.max = balance;
        }
    }
});

const DB_NAME = 'star-offline-forms';
const STORE = 'queue';

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

const put = (entry) => withStore('readwrite', (store) => store.put(entry));
const all = () => withStore('readonly', (store) => store.getAll());
const remove = (id) => withStore('readwrite', (store) => store.delete(id));

const updateBadges = async () => {
    const items = await all();
    const conflicts = items.filter((item) => item.conflict);
    document.querySelectorAll('[data-offline-count]').forEach((el) => {
        el.textContent = items.length;
    });
    document.querySelectorAll('[data-offline-badge]').forEach((el) => {
        el.classList.toggle('hidden', items.length === 0 && navigator.onLine);
    });
    document.querySelectorAll('[data-offline-conflict]').forEach((el) => {
        el.classList.toggle('hidden', conflicts.length === 0);
        el.textContent = `${conflicts.length} konflik`;
        el.title = conflicts.map((item) => `${item.label}: ${item.conflict}`).join('\n');
    });
    document.querySelectorAll('[data-offline-nav-badge]').forEach((el) => {
        el.classList.toggle('hidden', items.length === 0);
    });
    document.querySelectorAll('[data-offline-state]').forEach((el) => {
        el.textContent = navigator.onLine ? (items.length ? `${items.length} antrean` : 'Online') : 'Offline';
    });
};

const canSync = () => Boolean(document.querySelector('[data-offline-badge]') || document.querySelector('[data-offline-page]'));

const syncQueue = async () => {
    if (!navigator.onLine || !canSync()) {
        await updateBadges();
        return 0;
    }

    const items = await all();
    for (const entry of items) {
        const formData = new FormData();
        Object.entries(entry.fields).forEach(([key, value]) => formData.append(key, value));
        entry.files.forEach((file) => formData.append(file.field, file.blob, file.name));

        try {
            const response = await fetch(entry.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
            });

            if (response.redirected && response.url.includes('/login')) {
                continue;
            }

            if (response.ok) {
                await remove(entry.id);
            } else {
                let message = 'Data ditolak server.';
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
                } else if (response.status === 422) {
                    await remove(entry.id);
                    alert(message);
                }
            }
        } catch (error) {
            // Keep queued and retry later.
        }
    }

    await updateBadges();
    return (await all()).length;
};

const enqueueForm = async (form) => {
    const formData = new FormData(form);
    const fields = {};
    const files = [];

    for (const [key, value] of formData.entries()) {
        if (value instanceof File) {
            if (value.size > 0) {
                files.push({ field: key, blob: value, name: value.name });
            }
        } else {
            fields[key] = value;
        }
    }

    await put({
        id: crypto.randomUUID(),
        action: form.action,
        fields,
        files,
        label: form.dataset.offlineLabel || 'Form',
        createdAt: Date.now(),
    });
};

document.addEventListener('submit', async (event) => {
    const form = event.target.closest('form[data-offline-form]');
    if (!form) return;

    event.preventDefault();
    await enqueueForm(form);
    await updateBadges();
    const remaining = await syncQueue();

    if (navigator.onLine && remaining === 0) {
        window.location.reload();
    }
});

window.addEventListener('online', () => {
    updateBadges();
    syncQueue();
});
window.addEventListener('offline', updateBadges);

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
}

updateBadges().then(syncQueue);
