import AirDatepicker from 'air-datepicker';
import 'air-datepicker/air-datepicker.css';
import { api, notify, explainError } from '../lib/http';
export const uzLocale = { days: ['Yakshanba', 'Dushanba', 'Seshanba', 'Chorshanba', 'Payshanba', 'Juma', 'Shanba'], daysShort: ['Yak', 'Du', 'Se', 'Cho', 'Pay', 'Ju', 'Sha'], daysMin: ['Ya', 'Du', 'Se', 'Ch', 'Pa', 'Ju', 'Sh'], months: ['Yanvar', 'Fevral', 'Mart', 'Aprel', 'May', 'Iyun', 'Iyul', 'Avgust', 'Sentabr', 'Oktabr', 'Noyabr', 'Dekabr'], monthsShort: ['Yan', 'Fev', 'Mar', 'Apr', 'May', 'Iyn', 'Iyl', 'Avg', 'Sen', 'Okt', 'Noy', 'Dek'], today: 'Bugun', clear: 'Tozalash', dateFormat: 'yyyy-MM-dd', timeFormat: 'HH:mm', firstDay: 1 };
export function mount(node) {
    const start = node.querySelector('[data-rental-start]'), end = node.querySelector('[data-rental-end]'), status = node.querySelector('[data-availability]'), button = node.querySelector('[data-rental-book]');
    let available = false, sequence = 0, requestKey = null, requestSignature = '';
    const interval = () => ({ starts_at: `${start.value}T09:00:00+05:00`, ends_at: `${end.value}T09:00:00+05:00` });
    async function check() {
        available = false;
        if (button) button.disabled = true;
        const id = ++sequence;
        if (!start.value || !end.value || end.value <= start.value) { status.textContent = 'Tugash sanasi boshlanishdan keyin bo‘lishi kerak.'; return; }
        const dates = interval();
        if ((Date.parse(dates.ends_at) - Date.parse(dates.starts_at)) / 86400000 > 93) { status.textContent = 'Ko‘pi bilan 93 kunlik oraliqni tanlang.'; return; }
        status.textContent = 'Bandlik tekshirilmoqda…';
        try {
            const { data } = await api.get(`/listings/${node.dataset.listing}/availability`, { params: { from: dates.starts_at, to: dates.ends_at } });
            if (id !== sequence) return;
            available = data.data.busy.length === 0;
            status.textContent = available ? 'Tanlangan muddat hozircha bo‘sh. Bron so‘rovi yuborishingiz mumkin.' : 'Bu oraliqda band kunlar bor. Boshqa muddat tanlang.';
            if (button) button.disabled = !available;
        } catch (error) { if (id === sequence) status.textContent = explainError(error); }
    }
    [start, end].forEach(input => {
        input.type = 'text'; input.placeholder = 'YYYY-MM-DD';
        new AirDatepicker(input, { locale: uzLocale, autoClose: true, minDate: new Date(), onSelect: check });
        input.addEventListener('change', check);
    });
    if (button) {
        button.disabled = true;
        button.addEventListener('click', async () => {
            if (!available) return;
            button.disabled = true;
            const payload = { ...interval(), driver_option: node.querySelector('[data-rental-driver]').value };
            const signature = JSON.stringify(payload);
            if (signature !== requestSignature) { requestSignature = signature; requestKey = crypto.randomUUID(); }
            try {
                await api.post(`/listings/${node.dataset.listing}/reservations`, { ...payload, client_request_id: requestKey });
                available = false; status.textContent = 'Bron so‘rovi yuborildi. Sotuvchi tasdiqlashini kuting.'; notify('Bron so‘rovi yuborildi.');
            } catch (error) { status.textContent = explainError(error); button.disabled = false; }
        });
    }
}
