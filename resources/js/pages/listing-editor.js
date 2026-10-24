import TomSelect from 'tom-select';
import 'tom-select/dist/css/tom-select.css';
import IMask from 'imask';
import * as FilePond from 'filepond';
import ImagePreview from 'filepond-plugin-image-preview';
import ValidateSize from 'filepond-plugin-file-validate-size';
import ValidateType from 'filepond-plugin-file-validate-type';
import 'filepond/dist/filepond.min.css';
import 'filepond-plugin-image-preview/dist/filepond-plugin-image-preview.css';
import '../../css/listing-editor.css';
import { api, notify, explainError } from '../lib/http';
import { get, set, scalar, schemaParams } from '../lib/form-data';

FilePond.registerPlugin(ImagePreview, ValidateSize, ValidateType);
const labels = { cars: 'Avtomobil', parts: 'Zapchast', moto: 'Moto', gasoline: 'Benzin', diesel: 'Dizel', electric: 'Elektr', hybrid: 'Gibrid', lpg: 'Propan', cng: 'Metan', manual: 'Mexanika', automatic: 'Avtomat', cvt: 'Variator', robotized: 'Robot', semi_automatic: 'Yarim avtomat', fwd: 'Old', rwd: 'Orqa', awd: 'To‘liq' };
function el(tag, className, text) { const node = document.createElement(tag); if (className) node.className = className; if (text !== undefined) node.textContent = text; return node; }
function option(value, label) { const node = el('option', '', labels[value] || label); node.value = value; return node; }

export async function mount(form) {
    const state = JSON.parse(form.dataset.initial);
    const businesses = JSON.parse(form.dataset.businesses);
    const guided = form.dataset.guided === 'true';
    const verifiedPhone = form.dataset.verifiedPhone;
    if (verifiedPhone) state.contact_phone = businesses.find(item => item.id === Number(state.business_id))?.verified_contact_phone || verifiedPhone;
    const container = form.querySelector('[data-schema-fields]'), errors = form.querySelector('[data-form-errors]'), button = form.querySelector('[data-save-listing]'), status = form.querySelector('[data-save-status]');
    let schema, sequence = 0, abort, controls = [], selects = [], masks = [], catalog = [];
    let existing = state.images || [];
    const pond = FilePond.create(form.querySelector('[data-photo-upload]'), { allowMultiple: true, maxFiles: 10, maxFileSize: '5MB', acceptedFileTypes: ['image/jpeg', 'image/png', 'image/webp'], instantUpload: false, allowProcess: false, allowReorder: true, credits: false, labelIdle: 'Rasmlarni shu yerga tashlang yoki <span class="filepond--label-action">tanlang</span>', labelFileTypeNotAllowed: 'JPG, PNG yoki WebP tanlang', labelMaxFileSizeExceeded: 'Rasm hajmi juda katta', labelMaxFileSize: 'Eng katta hajm: {filesize}', labelButtonRemoveItem: 'Olib tashlash', labelTapToUndo: 'Qaytarish' });

    function showImages() {
        const target = form.querySelector('[data-existing-images]'); target.replaceChildren();
        existing.forEach(image => {
            const wrapper = el('div', 'relative');
            const img = el('img', 'aspect-square w-full rounded-lg object-cover'); img.src = image.thumbnail_url || image.url; img.alt = 'E’lon rasmi'; img.loading = 'lazy';
            const remove = el('button', 'absolute right-1 top-1 rounded bg-white px-2 py-1 text-sm', '×'); remove.type = 'button'; remove.setAttribute('aria-label', 'Rasmni olib tashlash');
            remove.addEventListener('click', async () => {
                remove.disabled = true;
                try { await api.delete(`/me/listings/${form.dataset.listingId}/images/${image.id}`); existing = existing.filter(item => item.id !== image.id); showImages(); }
                catch (error) { notify(explainError(error)); remove.disabled = false; }
            });
            wrapper.append(img, remove); target.append(wrapper);
        });
    }
    showImages();

    function collect() {
        controls.forEach(control => set(state, control.path, control.read()));
    }
    function selectControl(select, settings = {}) {
        const instance = new TomSelect(select, { maxOptions: 50, create: false, placeholder: 'Tanlang', ...settings }); selects.push(instance); return instance;
    }
    function vehiclePicker() {
        const wrapper = el('div', 'sm:col-span-2'); wrapper.append(el('p', 'mb-3 text-sm font-semibold', 'Transport katalogidan tanlash (ixtiyoriy)'));
        const grid = el('div', 'grid gap-3 sm:grid-cols-2'); wrapper.append(grid);
        const kinds = ['makes', 'models', 'generations', 'variants'], parents = [null, 'vehicle_make_id', 'vehicle_model_id', 'vehicle_generation_id'];
        const names = ['Marka', 'Model', 'Avlod', 'Komplektatsiya']; const instances = [];
        kinds.forEach((kind, index) => {
            const label = el('label', 'field', names[index]); const select = el('select', 'select'); select.setAttribute('aria-label', `${names[index]} (katalog)`); select.append(option('', 'Tanlang')); label.append(select); grid.append(label);
            const saved = catalog[index];
            if (saved) select.append(option(saved.id, saved.name));
            if (saved) select.value = saved.id;
            const instance = selectControl(select, {
                valueField: 'id', labelField: 'name', searchField: 'name', preload: 'focus',
                load(query, callback) {
                    if (index && !catalog[index - 1]) return callback();
                    const params = { section: state.section, q: query, per_page: 50 };
                    if (!query) delete params.q;
                    if (index) params[parents[index]] = catalog[index - 1].id;
                    api.get(`/catalog/vehicles/${kind}`, { params }).then(({ data }) => callback(data.data)).catch(() => callback());
                },
                onChange(value) {
                    catalog[index] = value ? { id: value, name: this.options[value]?.name || '' } : null;
                    for (let next = index + 1; next < 4; next++) { catalog[next] = null; instances[next]?.clear(true); instances[next]?.clearOptions(); instances[next]?.disable(); }
                    if (value && index < 3) instances[index + 1]?.enable();
                    collect(); state.vehicle_variant_id = index === 3 && value ? Number(value) : null;
                    if (index === 3 || get(state, 'details.brand')) refresh();
                },
            });
            instances.push(instance); if (index && !catalog[index - 1]) instance.disable();
        });
        grid.append(el('p', 'muted sm:col-span-2', 'Katalogda bo‘lmasa, marka va modelni quyida qo‘lda kiriting.'));
        if (state.vehicle_variant_id) {
            const manual = el('button', 'btn btn-ghost btn-sm', 'Katalog tanlovini tozalash'); manual.type = 'button';
            manual.onclick = () => { collect(); state.vehicle_variant_id = null; catalog = []; refresh(); }; grid.append(manual);
        }
        return wrapper;
    }
    function build(path, field, parent, value = get(state, path)) {
        if (path.includes('*') || ['submit', 'vehicle_variant_id'].includes(path)) return;
        if (guided && (path === 'section' || (['business_id', 'business_branch_id'].includes(path) && !businesses.length && !state.business_id))) return;
        if (field.value_from) value = get(state, field.value_from);
        if (value === undefined) value = field.default;
        const id = `field-${path.replaceAll('.', '-')}`;
        const label = el('label', 'field' + (['description', 'details.compatible_vehicles'].includes(path) ? ' sm:col-span-2' : ''));
        label.htmlFor = id; label.append(el('span', '', field.label.replace(' (null — cheklanmagan)', '') + (field.required ? ' *' : '')));
        let input, read;
        if (field.type === 'list' && path === 'details.compatible_vehicles') {
            const wrapper = el('div', 'space-y-3'); const rows = [];
            const addRow = (data = {}) => {
                const row = el('div', 'grid gap-3 rounded-xl bg-slate-50 p-3 sm:grid-cols-2'); const entries = [];
                ['brand', 'model', 'generation', 'year_from', 'year_to', 'engine'].forEach(key => {
                    const info = schema.fields[`${path}.*.${key}`];
                    const cell = el('label', 'field', info.label); const control = el('input', 'input input-sm'); control.setAttribute('aria-label', `Mos transport: ${info.label}`); control.type = info.type === 'integer' ? 'number' : 'text'; control.value = data[key] ?? ''; if (info.constraints.max) control[control.type === 'number' ? 'max' : 'maxLength'] = info.constraints.max; cell.append(control); row.append(cell); entries.push({ key, control, info });
                });
                const remove = el('button', 'btn btn-ghost btn-sm', 'Transportni olib tashlash'); remove.type = 'button'; remove.onclick = () => { row.remove(); rows.splice(rows.indexOf(entries), 1); }; row.append(remove); wrapper.append(row); rows.push(entries);
            };
            (Array.isArray(value) ? value : []).forEach(addRow);
            const add = el('button', 'btn btn-ghost btn-sm', '+ Mos transport qo‘shish'); add.type = 'button'; add.onclick = () => { if (rows.length < 50) addRow(); }; wrapper.append(add); label.append(wrapper);
            read = () => rows.map(entries => Object.fromEntries(entries.map(({ key, control, info }) => [key, scalar(control.value, info.type)]).filter(([, entry]) => entry !== null)));
        } else if (field.type === 'list') {
            input = el('select', 'select'); input.multiple = true;
            const options = schema.fields[`${path}.*`]?.options;
            const selected = Array.isArray(value) ? value : [];
            Object.entries(options || Object.fromEntries(selected.map(item => [item, item]))).forEach(([key, text]) => input.append(option(key, text)));
            [...input.options].forEach(item => item.selected = selected.includes(item.value));
            label.append(input); selectControl(input, { create: !options, maxItems: field.constraints.max || null, plugins: ['remove_button'], delimiter: ',', onItemAdd() { this.setTextboxValue(''); } });
            read = () => [...input.selectedOptions].map(item => item.value);
        } else if (field.type === 'boolean') {
            input = el('input', 'checkbox'); input.type = 'checkbox'; input.checked = value === true || value === 1; label.append(input); read = () => input.checked;
        } else if (field.options || ['business_id', 'business_branch_id'].includes(path)) {
            input = el('select', 'select'); input.append(option('', field.required ? 'Tanlang' : 'Tanlanmagan'));
            let options = field.options;
            if (path === 'business_id') options = Object.fromEntries(businesses.map(item => [item.id, item.name]));
            if (path === 'business_branch_id') options = Object.fromEntries((businesses.find(item => item.id === Number(state.business_id))?.branches || []).map(item => [item.id, item.name]));
            Object.entries(options || {}).forEach(([key, text]) => input.append(option(key, text))); input.value = value ?? ''; label.append(input);
            read = () => scalar(input.value, ['business_id', 'business_branch_id'].includes(path) ? 'integer' : field.type);
        } else if (path === 'part_product_id') {
            input = el('select', 'select'); input.append(option('', 'Yangi / mustaqil detal'));
            if (value) input.append(option(value, state.part_product?.title || 'Tanlangan detal'));
            input.value = value ?? ''; label.append(input);
            selectControl(input, { valueField: 'id', labelField: 'title', searchField: 'title', preload: 'focus', load(query, callback) { api.get('/parts', { params: query ? { q: query } : {} }).then(({ data }) => callback(data.data)).catch(() => callback()); }, onChange(value) { collect(); state.part_product_id = value ? Number(value) : null; refresh(); } });
            read = () => input.value ? Number(input.value) : null;
        } else {
            input = el(path === 'description' || path.endsWith('_terms') ? 'textarea' : 'input', path === 'description' || path.endsWith('_terms') ? 'textarea min-h-28' : 'input');
            if (input.tagName === 'INPUT') input.type = path === 'contact_phone' ? 'tel' : (['integer', 'number', 'money'].includes(field.type) ? 'number' : field.type === 'date' ? 'date' : 'text');
            input.value = value ?? '';
            if (input.type === 'number') { input.step = field.type === 'integer' ? '1' : '0.01'; if (field.constraints.min !== undefined) input.min = field.constraints.min; if (field.constraints.max !== undefined) input.max = field.constraints.max; }
            else if (field.constraints.max && field.type === 'string') input.maxLength = field.constraints.max;
            if (field.value_from || schema.read_only_fields?.includes(path)) input.readOnly = true;
            if (path === 'contact_phone' && verifiedPhone) input.readOnly = true;
            label.append(input); read = () => scalar(input.value, field.type);
            if (path === 'contact_phone') { input.autocomplete = 'tel'; const mask = IMask(input, { mask: '+{998} 00 000 00 00' }); masks.push(mask); read = () => mask.unmaskedValue ? `+${mask.unmaskedValue}` : null; }
        }
        if (input) {
            input.id = id; input.dataset.path = path;
            if (field.required && field.type !== 'boolean') input.required = true;
            input.addEventListener('change', () => {
                collect();
                if (path === 'section') { state.offer_type = 'sale'; state.details = {}; state.vehicle_variant_id = null; state.part_product_id = null; catalog = []; }
                if (path === 'business_id') { state.business_branch_id = null; if (verifiedPhone) state.contact_phone = businesses.find(item => item.id === Number(state.business_id))?.verified_contact_phone || verifiedPhone; }
                if (schema.refresh_on.includes(path) || path === 'business_id') refresh();
            });
        }
        controls.push({ path, read }); parent.append(label);
    }
    async function refresh() {
        const id = ++sequence;
        button.disabled = true;
        abort?.abort(); abort = new AbortController();
        errors.classList.add('hidden');
        try {
            const { data } = await api.get('/catalog/form', { params: schemaParams(state), signal: abort.signal });
            if (id !== sequence) return;
            schema = data.data;
            for (const path of schema.read_only_fields || []) set(state, path, get(schema.prefill, path));
            selects.forEach(instance => instance.destroy()); masks.forEach(instance => instance.destroy()); selects = []; masks = []; controls = [];
            container.replaceChildren();
            const groups = {};
            [['general', 'E’lon haqida'], ['details', 'Texnik ma’lumotlar'], ['rental', 'Arenda shartlari'], ['contact', 'Aloqa va manzil']].forEach(([key, title]) => {
                const section = el('fieldset', 'panel p-5 sm:p-6 listing-editor-group'); section.id = key === 'general' ? 'listing-about' : `listing-${key}`; section.append(el('legend', 'px-2 text-lg font-bold', title)); const grid = el('div', 'grid gap-5 sm:grid-cols-2'); section.append(grid); groups[key] = { section, grid };
            });
            if (['cars', 'moto'].includes(state.section)) groups.details.grid.append(vehiclePicker());
            Object.entries(schema.fields).forEach(([path, field]) => build(path, field, groups[['city', 'region', 'contact_phone'].includes(path) ? 'contact' : path.startsWith('details.') ? 'details' : path.startsWith('rental.') ? 'rental' : 'general'].grid));
            Object.values(groups).forEach(({ section, grid }) => { if (grid.childElementCount) container.append(section); });
            button.disabled = false;
        } catch (error) { if (error.code !== 'ERR_CANCELED') { errors.textContent = explainError(error); errors.classList.remove('hidden'); } }
    }
    form.querySelectorAll('[data-listing-section]').forEach(input => input.addEventListener('change', () => {
        if (!input.checked || input.disabled || input.value === state.section) return;
        collect(); state.section = input.value; state.offer_type = 'sale'; state.details = {}; state.vehicle_variant_id = null; state.part_product_id = null; catalog = [];
        refresh();
    }));
    form.addEventListener('submit', async event => {
        event.preventDefault(); if (button.disabled || !schema) return;
        collect();
        const payload = { details: {}, submit: form.querySelector('[data-submit-moderation]').checked };
        Object.entries(schema.fields).forEach(([path, field]) => {
            if (path.includes('*') || path === 'submit') return;
            let value = field.value_from ? get(state, field.value_from) : get(state, path);
            if (value === undefined) value = field.default;
            if (value !== null && value !== '' && value !== undefined && (!Array.isArray(value) || value.length)) set(payload, path, value);
        });
        if (form.dataset.listingId && state.section === 'parts') payload.expected_stock_version = state.details?.stock_version ?? 0;
        if (existing.length + pond.getFiles().length > 10) { notify('Jami 10 tagacha rasm yuklash mumkin.'); return; }
        button.disabled = true; status.textContent = 'Saqlanmoqda…'; errors.classList.add('hidden');
        try {
            const { data } = form.dataset.listingId ? await api.put(`/me/listings/${form.dataset.listingId}`, payload) : await api.post('/me/listings', payload);
            form.dataset.listingId = data.data.id; state.details = data.data.details;
            history.replaceState(null, '', `/kabinet/elonlar/${data.data.id}/tahrirlash`);
            const files = pond.getFiles();
            if (files.length) {
                status.textContent = 'E’lon saqlandi. Rasmlar yuklanmoqda…';
                const body = new FormData(); files.forEach(file => body.append('images[]', file.file));
                const result = await api.post(`/me/listings/${data.data.id}/images`, body, { timeout: 120000 });
                existing = result.data.data.images; pond.removeFiles(); showImages();
            }
            status.textContent = payload.submit ? 'Saqlandi va moderatsiyaga yuborildi.' : 'Qoralama saqlandi.'; notify(status.textContent);
            const saved = form.querySelector('[data-listing-saved]'); if (saved) saved.hidden = false;
        } catch (error) {
            errors.textContent = explainError(error); errors.classList.remove('hidden'); errors.scrollIntoView({ block: 'center', behavior: 'smooth' }); status.textContent = form.dataset.listingId ? 'E’lon saqlangan. Xatoni tuzatib qayta saqlashingiz mumkin.' : '';
            Object.keys(error.response?.data?.errors || {}).forEach(path => { const control = [...form.querySelectorAll('[data-path]')].find(input => input.dataset.path === path); if (control) control.setAttribute('aria-invalid', 'true'); });
        } finally { button.disabled = false; }
    });
    await refresh();
}
