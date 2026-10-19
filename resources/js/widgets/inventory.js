import { TabulatorFull as Tabulator } from 'tabulator-tables';
import 'tabulator-tables/dist/css/tabulator_simple.min.css';
import { api, notify, explainError } from '../lib/http';

export async function mount(node) {
    const status = node.querySelector('[data-inventory-status]'), more = node.querySelector('[data-inventory-more]');
    let nextPage = 1, fetching = false;
    const table = new Tabulator(node.querySelector('[data-inventory-grid]'), {
        layout: 'fitColumns', height: 480, placeholder: 'Hozircha mahsulotlar yo‘q', index: 'id',
        columns: [
            { title: 'E’lon', field: 'title', minWidth: 220, formatter(cell) { const a = document.createElement('a'); a.href = `/kabinet/elonlar/${cell.getRow().getData().id}/tahrirlash`; a.textContent = cell.getValue(); a.className = 'font-semibold underline'; return a; } },
            { title: 'Holati', field: 'status', width: 130, formatter(cell) { return ({ draft: 'Qoralama', pending: 'Tekshiruvda', published: 'Faol', rejected: 'Rad etilgan', sold: 'Sotilgan', archived: 'Arxiv' })[cell.getValue()] || ''; } },
            { title: 'Narx', field: 'price', width: 130 }, { title: 'Valyuta', field: 'currency', width: 90 },
            { title: 'Qoldiq', field: 'stock', width: 110, editor: 'number', editable: cell => node.dataset.canStock === '1' && cell.getRow().getData().section === 'parts' && !cell.getRow().getData().saving, editorParams: { min: 0, max: 1000000, step: 1 } },
        ],
    });
    table.on('cellEdited', async cell => {
        if (cell.getField() !== 'stock') return;
        const row = cell.getRow(), data = row.getData(); data.saving = true;
        try {
            const result = await api.patch(`/me/businesses/${node.dataset.business}/inventory/${data.id}/stock`, { stock_quantity: Number(cell.getValue()), expected_version: data.version });
            await row.update({ stock: result.data.data.details.stock_quantity, version: result.data.data.details.stock_version, saving: false });
            notify('Ombor qoldig‘i saqlandi.');
        } catch (error) { cell.restoreOldValue(); data.saving = false; notify(explainError(error)); }
    });
    async function fetchPage() {
        if (fetching || !nextPage) return;
        fetching = true; more.disabled = true;
        try {
            const { data } = await api.get(`/me/businesses/${node.dataset.business}/inventory`, { params: { page: nextPage } });
            const rows = data.data.map(item => ({ id: item.id, title: item.title, status: item.status, section: item.section, price: item.price, currency: item.currency, stock: item.details?.stock_quantity ?? '', version: item.details?.stock_version }));
            await table.updateOrAddData(rows);
            nextPage = data.links?.next ? nextPage + 1 : null; more.hidden = !nextPage;
            status.textContent = `${table.getDataCount()} ta mahsulot yuklandi. ${node.dataset.canStock === '1' ? 'Qoldiq katagini bosib tahrirlashingiz mumkin.' : ''}`;
        } catch (error) { status.textContent = explainError(error); } finally { fetching = false; more.disabled = false; }
    }
    table.on('tableBuilt', fetchPage); more.addEventListener('click', fetchPage);
}
