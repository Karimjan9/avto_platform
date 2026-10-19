import Papa from 'papaparse';
import { api, explainError } from '../lib/http';
import { set } from '../lib/form-data';

const sample = { section: 'parts', title: 'Mahsulot nomi', description: 'Mahsulot haqida batafsil ma’lumot', condition: 'new', offer_type: 'sale', price: '100000', currency: 'UZS', city: 'Buxoro', contact_phone: '+998901234567', 'details.category': 'other', 'details.manufacturer': '', 'details.part_number': '001234', 'details.stock_quantity': '10' };
const allowed = new Set(Object.keys(sample));
export function mount(node) {
    const file = node.querySelector('[data-csv-file]'), preview = node.querySelector('[data-csv-preview]'), status = node.querySelector('[data-csv-status]'), save = node.querySelector('[data-csv-save]');
    let rows = [], offset = 0, busy = false;
    node.querySelector('[data-csv-template]').onclick = () => {
        const url = URL.createObjectURL(new Blob(['\uFEFF' + Papa.unparse([sample])], { type: 'text/csv;charset=utf-8' }));
        const a = document.createElement('a'); a.href = url; a.download = 'buxoroavto-zapchast-namuna.csv'; a.click(); setTimeout(() => URL.revokeObjectURL(url), 1000);
    };
    file.onchange = () => {
        const chosen = file.files[0]; rows = []; offset = 0; save.disabled = true; preview.replaceChildren();
        if (!chosen) return;
        if (chosen.size > 2 * 1024 * 1024) { status.textContent = 'CSV fayl hajmi 2 MB gacha bo‘lishi kerak.'; return; }
        status.textContent = 'Fayl tekshirilmoqda…';
        Papa.parse(chosen, { header: true, skipEmptyLines: 'greedy', worker: true, dynamicTyping: false,
            complete(result) {
                if (result.errors.length) { status.textContent = 'CSV tuzilishida xatolik bor: ' + result.errors.map(item => `qator ${(item.row ?? 0) + 2}`).join(', '); return; }
                if (!result.data.length || result.data.length > 1000) { status.textContent = 'Faylda 1 dan 1000 tagacha mahsulot bo‘lishi kerak.'; return; }
                const fields = result.meta.fields || [];
                if (fields.some(key => !allowed.has(key))) { status.textContent = 'Noma’lum ustunlar bor. Namuna fayldagi ustun nomlarini ishlating.'; return; }
                const required = ['title', 'description', 'price', 'currency', 'city', 'contact_phone'];
                const bad = result.data.findIndex(row => required.some(key => !row[key]?.trim()) || (row.section && row.section !== 'parts') || !Number.isFinite(Number(row.price)) || Number(row.price) < 0 || !['UZS', 'USD'].includes(row.currency));
                if (bad >= 0) { status.textContent = `${bad + 2}-qatorda majburiy maydon yoki narx/valyuta xatosi bor.`; return; }
                rows = result.data.map(row => {
                    const payload = { section: 'parts', condition: 'new', offer_type: 'sale', details: {}, submit: false };
                    Object.entries(row).forEach(([key, value]) => { if (value.trim() !== '') set(payload, key, key === 'details.stock_quantity' ? Number(value) : value.trim()); });
                    return payload;
                });
                const table = document.createElement('table'); table.className = 'table table-sm';
                const head = document.createElement('tr'); ['Sarlavha', 'Narx', 'Valyuta', 'Qoldiq'].forEach(title => { const th = document.createElement('th'); th.textContent = title; head.append(th); }); table.append(head);
                rows.slice(0, 10).forEach(row => { const tr = document.createElement('tr'); [row.title, row.price, row.currency, row.details.stock_quantity ?? 0].forEach(value => { const td = document.createElement('td'); td.textContent = value; tr.append(td); }); table.append(tr); });
                preview.append(table); status.textContent = `${rows.length} ta mahsulot tayyor. Dastlabki 10 tasi ko‘rsatilgan. Import qoralama sifatida saqlanadi.`; save.disabled = false;
            }, error() { status.textContent = 'Faylni o‘qib bo‘lmadi.'; },
        });
    };
    save.onclick = async () => {
        if (busy || !rows.length) return;
        busy = true; save.disabled = true; file.disabled = true;
        let uncertain = false;
        try {
            while (offset < rows.length) {
                status.textContent = `${offset} / ${rows.length} ta saqlandi…`;
                const chunk = rows.slice(offset, offset + 50);
                await api.post(`/me/businesses/${node.dataset.business}/import`, { rows: chunk }, { timeout: 60000 });
                offset += chunk.length;
            }
            status.textContent = `${offset} ta mahsulot qoralama sifatida saqlandi. Jadvalni yangilab ko‘rishingiz mumkin.`;
        } catch (error) {
            uncertain = !error.response || error.response.status >= 500;
            status.textContent = `${offset} ta mahsulot saqlangan. ` + explainError(error) + (uncertain ? ' Oxirgi guruh holati noma’lum; takroriy importdan oldin omborni tekshiring.' : ' Joriy 50 qatorlik guruh saqlanmadi.');
        } finally { busy = false; file.disabled = false; save.disabled = uncertain || offset >= rows.length; }
    };
}
