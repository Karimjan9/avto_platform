import { Calendar } from 'fullcalendar';
import themePlugin from 'fullcalendar/themes/monarch';
import dayGridPlugin from 'fullcalendar/daygrid';
import uzLocale from 'fullcalendar/locales/uz';
import 'fullcalendar/skeleton.css';
import 'fullcalendar/themes/monarch/theme.css';
import 'fullcalendar/themes/monarch/palettes/green.css';
import { api, explainError } from '../lib/http';

export function mount(node) {
    const status = node.querySelector('[data-calendar-status]');
    const calendar = new Calendar(node.querySelector('[data-calendar-view]'), {
        plugins: [themePlugin, dayGridPlugin], initialView: 'dayGridMonth', timeZone: 'Asia/Tashkent', firstDay: 1,
        locale: uzLocale,
        titleFormat({ date }) {
            return `${['Yanvar', 'Fevral', 'Mart', 'Aprel', 'May', 'Iyun', 'Iyul', 'Avgust', 'Sentabr', 'Oktabr', 'Noyabr', 'Dekabr'][date.month]} ${date.year}`;
        },
        dayHeaderFormat({ date }) {
            return ['Yak', 'Du', 'Se', 'Cho', 'Pay', 'Ju', 'Sha'][date.marker.getUTCDay()];
        },
        headerToolbar: { left: 'prev,next', center: 'title', right: 'today' }, height: 'auto', editable: false,
        async events(info, success, failure) {
            try {
                let page = 1, events = [], hasMore;
                do {
                    const { data } = await api.get(`/me/listings/${node.dataset.listing}/calendar`, { params: { from: info.startStr, to: info.endStr, page } });
                    events.push(...data.data.filter(row => ['pending', 'confirmed'].includes(row.status) && (row.status !== 'pending' || Date.parse(row.expires_at) > Date.now())).map(row => ({ id: String(row.id), title: row.source === 'block' ? 'Band qilingan' : row.status === 'confirmed' ? 'Tasdiqlangan bron' : 'Tasdiq kutilmoqda', start: row.starts_at, end: row.ends_at, color: row.status === 'confirmed' ? '#17675c' : '#a16207' })));
                    hasMore = Boolean(data.next_page_url); page++;
                } while (hasMore && page <= 20);
                success(events); status.textContent = hasMore ? 'Bandlik ko‘p. To‘liq ma’lumot uchun kichikroq oraliq tanlang.' : `${events.length} ta faol bandlov. Vaqtlar Asia/Tashkent bo‘yicha.`;
            } catch (error) { status.textContent = explainError(error); failure(error); }
        },
    });
    calendar.render();
}
