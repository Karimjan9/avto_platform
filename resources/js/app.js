import './bootstrap';
import Alpine from 'alpinejs';
import focus from '@alpinejs/focus';
import { createIcons, UserRound, Plus, Menu, CarFront, Wrench, Bike, ArrowUpRight, Search, SlidersHorizontal, Image, Phone } from 'lucide';
import { api, notify, explainError } from './lib/http';

Alpine.plugin(focus);
Alpine.data('toast', () => ({
    message: '', timer: null,
    show(message) { this.message = message; clearTimeout(this.timer); this.timer = setTimeout(() => { this.message = ''; }, 6500); },
}));
Alpine.start();
createIcons({ icons: { UserRound, Plus, Menu, CarFront, Wrench, Bike, ArrowUpRight, Search, SlidersHorizontal, Image, Phone }, nameAttr: 'data-icon', attrs: { 'aria-hidden': 'true', 'stroke-width': 1.7 } });

function load(selector, importer) {
    const nodes = document.querySelectorAll(selector);
    if (nodes.length) importer().then(({ mount }) => Promise.all([...nodes].map(mount))).catch(error => {
        console.error(error);
        notify('Sahifaning bir qismini yuklab bo‘lmadi. Sahifani yangilang.');
    });
}
load('[data-listing-editor]', () => import('./pages/listing-editor'));
load('[data-gallery]', () => import('./widgets/gallery'));
load('[data-rental-picker]', () => import('./widgets/rental-picker'));
load('[data-rental-calendar]', () => import('./widgets/calendar'));
load('[data-inventory]', () => import('./widgets/inventory'));
load('[data-csv-import]', () => import('./widgets/csv-import'));
load('[data-status-chart]', () => import('./widgets/status-chart'));
document.querySelectorAll('[data-price-range]').forEach(node => {
    const observer = new IntersectionObserver(entries => {
        if (entries.some(entry => entry.isIntersecting)) {
            observer.disconnect();
            import('./widgets/price-range').then(({ mount }) => mount(node)).catch(() => notify('Narx maydonlaridan foydalaning.'));
        }
    });
    observer.observe(node);
});
document.querySelectorAll('[data-filter-form]').forEach(form => form.addEventListener('submit', () => {
    for (const field of form.elements) if (field.name && field.value === '') field.disabled = true;
}));
document.querySelectorAll('[data-read-notification]').forEach(button => button.addEventListener('click', async () => {
    try {
        await api.patch(`/me/notifications/${button.dataset.readNotification}/read`);
        button.querySelector('span').textContent = button.querySelector('span').textContent.replace(/^● /, '');
        notify('Xabar o‘qilgan deb belgilandi.');
    } catch (error) { notify(explainError(error)); }
}));
document.querySelectorAll('[data-moderation]').forEach(panel => panel.querySelector('[data-moderate]').addEventListener('click', async event => {
    const button = event.currentTarget;
    button.disabled = true;
    try {
        await api.patch(`/admin/listings/${panel.dataset.listing}/moderation`, { status: panel.querySelector('[data-decision]').value, reason: panel.querySelector('[data-reason]').value || null });
        panel.remove(); notify('Qaror saqlandi.');
    } catch (error) { notify(explainError(error)); } finally { button.disabled = false; }
}));
