import TomSelect from 'tom-select';
import 'tom-select/dist/css/tom-select.css';

const wirePlaceholder = (ts, placeholder) => {
    const update = (hide) => {
        const value = ts.getValue();
        const empty = Array.isArray(value) ? value.length === 0 : !value;

        ts.wrapper.classList.toggle('ts-placeholder-hidden', hide && empty);
        ts.control_input.placeholder = hide && empty ? '' : (empty ? placeholder : '');
    };

    ts.on('focus', () => update(true));
    ts.on('open', () => update(true));
    ts.on('open', () => {
        if (!ts.dropdown.querySelector('.option') && Object.keys(ts.options).length > 0) {
            ts.refreshOptions(false);
        }

        ts.positionDropdown();
    });
    ts.on('blur', () => update(false));
    ts.on('close', () => update(false));
    ts.on('change', () => update(ts.isOpen));
    update(false);
};

const enhance = (el) => {
    if (el.tomselect || el.dataset.native !== undefined) {
        return;
    }

    const dialog = el.closest('dialog');
    const isMultiple = el.multiple;
    const required = el.hasAttribute('required');
    const canCreate = el.dataset.creatable !== undefined;

    if (required) {
        el.removeAttribute('required');
        el.dataset.required = '1';
    }

    const settings = {
        create: canCreate,
        createOnBlur: canCreate,
        persist: false,
        maxItems: isMultiple ? null : 1,
        allowEmptyOption: true,
        placeholder: el.dataset.placeholder || el.getAttribute('placeholder') || 'Pilih…',
        hidePlaceholder: true,
        dropdownParent: dialog || 'body',
        wrapperClass: ['ts-wrapper', ...el.classList].filter((name) => name !== 'input').join(' '),
        render: {
            option: (data, escape) => `<div class="ts-option">${escape(data.text)}</div>`,
            item: (data, escape) => `<div class="ts-item">${escape(data.text)}</div>`,
            option_create: (data, escape) => `<div class="ts-create">Tambah <b>${escape(data.input)}</b>…</div>`,
            no_results: () => '<div class="ts-no-results">Tidak ada hasil</div>',
            loading: () => '<div class="ts-no-results">Memuat…</div>',
        },
    };

    if (el.dataset.remote) {
        const url = el.dataset.remote;
        settings.valueField = 'value';
        settings.labelField = 'text';
        settings.searchField = ['text'];
        settings.preload = 'focus';
        settings.load = async (query, callback) => {
            try {
                const response = await fetch(`${url}${url.includes('?') ? '&' : '?'}q=${encodeURIComponent(query)}`, {
                    headers: { 'Accept': 'application/json' },
                });

                if (!response.ok) {
                    callback();

                    return;
                }

                const json = await response.json();
                callback(json.items || json);
            } catch (error) {
                callback();
            }
        };
        settings.shouldLoad = () => true;
    }

    const ts = new TomSelect(el, settings);
    wirePlaceholder(ts, settings.placeholder);
};

const enhanceAll = (root = document) => {
    root.querySelectorAll('select').forEach(enhance);
    root.querySelectorAll('input[data-autocomplete]').forEach((input) => {
        if (input.tomselect || input.dataset.native !== undefined) {
            return;
        }

        let options = [];

        if (input.dataset.options) {
            try {
                options = JSON.parse(input.dataset.options);
            } catch (error) {
                options = [];
            }
        }

        if (input.value) {
            options.unshift({ value: input.value, text: input.value });
        }

        const settings = {
            create: input.dataset.creatable !== undefined,
            createOnBlur: input.dataset.creatable !== undefined,
            persist: false,
            maxItems: 1,
            options,
            items: input.value ? [input.value] : [],
            placeholder: input.getAttribute('placeholder') || 'Ketik…',
            dropdownParent: input.closest('dialog') || 'body',
            render: {
                option: (data, escape) => `<div class="ts-option">${escape(data.text)}</div>`,
                item: (data, escape) => `<div class="ts-item">${escape(data.text)}</div>`,
                no_results: () => '<div class="ts-no-results">Tidak ada hasil</div>',
                loading: () => '<div class="ts-no-results">Memuat…</div>',
            },
        };

        if (input.dataset.remote) {
            const url = input.dataset.remote;
            settings.preload = 'focus';
            settings.load = async (query, callback) => {
                try {
                    const response = await fetch(`${url}${url.includes('?') ? '&' : '?'}q=${encodeURIComponent(query)}`, {
                        headers: { 'Accept': 'application/json' },
                    });
                    if (!response.ok) {
                        callback();

                        return;
                    }
                    const json = await response.json();
                    callback(json.items || json);
                } catch (error) {
                    callback();
                }
            };
            settings.shouldLoad = () => true;
        }

        const ts = new TomSelect(input, settings);
        wirePlaceholder(ts, settings.placeholder);
    });
};

// HTML5 required is not reliable on a Tom Select control: validate on submit instead.
document.addEventListener('submit', (event) => {
    const form = event.target;

    if (!(form instanceof HTMLFormElement) || form.dataset.skipSelectValidation === '1') {
        return;
    }

    const missing = [...form.querySelectorAll('[data-required="1"]')].find((el) => {
        const value = el.tomselect ? el.tomselect.getValue() : el.value;

        return Array.isArray(value) ? value.length === 0 : !value;
    });

    if (missing) {
        event.preventDefault();
        event.stopImmediatePropagation();
        alert('Lengkapi dulu pilihan yang wajib diisi.');
        missing.tomselect?.focus();
        missing.closest('dialog')?.querySelector('.ts-control')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
}, true);

window.starEnhanceSelects = enhanceAll;

enhanceAll();
