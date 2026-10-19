import PhotoSwipeLightbox from 'photoswipe/lightbox';
import 'photoswipe/style.css';
export function mount(node) {
    node.querySelectorAll('a').forEach(anchor => {
        const img = anchor.querySelector('img');
        const preset = Boolean(anchor.dataset.pswpWidth && anchor.dataset.pswpHeight);
        const measure = () => { if (!preset) { anchor.dataset.pswpWidth = img.naturalWidth || 1200; anchor.dataset.pswpHeight = img.naturalHeight || 900; } };
        measure(); img.addEventListener('load', measure, { once: true });
    });
    new PhotoSwipeLightbox({ gallery: node, children: 'a', pswpModule: () => import('photoswipe'), closeTitle: 'Yopish', zoomTitle: 'Kattalashtirish', arrowPrevTitle: 'Oldingi', arrowNextTitle: 'Keyingi', errorMsg: 'Rasmni yuklab bo‘lmadi.' }).init();
}
