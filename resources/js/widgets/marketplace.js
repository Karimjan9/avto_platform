import { api, notify } from '../lib/http';

const savedKey = 'buxoroavto.saved-listings.v1';
let savedItems = [];

function validItem(item) {
    if (!item || !Number.isSafeInteger(Number(item.id)) || Number(item.id) < 1 || typeof item.title !== 'string' || typeof item.price !== 'string') return null;
    try {
        const url = new URL(item.url, location.origin);
        if (url.origin !== location.origin || !/^\/elonlar\/\d+$/.test(url.pathname)) return null;
        let image = null;
        if (item.image) { const photo = new URL(item.image, location.origin); if (photo.origin === location.origin) image = photo.href; }
        return { id: Number(item.id), title: item.title.slice(0, 160), url: url.href, image, price: item.price.slice(0, 100) };
    } catch { return null; }
}

function readSaved() {
    try { const items = JSON.parse(localStorage.getItem(savedKey) || '[]'); return Array.isArray(items) ? items.map(validItem).filter(Boolean).slice(0, 50) : []; }
    catch { return []; }
}

function writeSaved() {
    try { localStorage.setItem(savedKey, JSON.stringify(savedItems)); }
    catch { notify('E’lon ushbu sahifada saqlandi. Brauzer xotirasiga yozib bo‘lmadi.'); }
    updateSaved();
}

function updateSaved() {
    const ids = new Set(savedItems.map(item => item.id));
    document.querySelectorAll('[data-save-card]').forEach(button => {
        try { const item = validItem(JSON.parse(button.dataset.savedItem)); if (item) button.setAttribute('aria-pressed', String(ids.has(item.id))); } catch { /* Ignore invalid card metadata. */ }
    });
    document.querySelectorAll('[data-saved-count]').forEach(count => { count.textContent = savedItems.length; count.hidden = savedItems.length === 0; });
    const content = document.querySelector('[data-saved-content]');
    if (!content) return;
    content.replaceChildren();
    if (!savedItems.length) {
        const empty = document.createElement('div'); empty.className = 'site-saved-empty';
        const title = document.createElement('h3'); title.textContent = 'Hozircha saqlangan e’lon yo‘q';
        const message = document.createElement('p'); message.textContent = 'Yoqtirgan e’loningizdagi yurak belgisini bosing. Uni shu yerdan topasiz.';
        const link = document.createElement('a'); link.href = '/elonlar'; link.className = 'btn btn-brand'; link.textContent = 'E’lonlarni ko‘rish';
        empty.append(title, message, link); content.append(empty); return;
    }
    savedItems.forEach(item => {
        const row = document.createElement('article'); row.className = 'site-saved-item';
        const photoLink = document.createElement('a'); photoLink.href = item.url; photoLink.setAttribute('aria-label', item.title);
        if (item.image) { const image = document.createElement('img'); image.src = item.image; image.alt = item.title; image.loading = 'lazy'; photoLink.append(image); }
        const body = document.createElement('div'); const link = document.createElement('a'); link.href = item.url; link.className = 'site-saved-item-title'; link.textContent = item.title;
        const price = document.createElement('p'); price.className = 'site-saved-item-price'; price.textContent = item.price; body.append(link, price);
        const remove = document.createElement('button'); remove.type = 'button'; remove.className = 'site-saved-remove'; remove.textContent = '×'; remove.setAttribute('aria-label', `${item.title} e’lonini olib tashlash`);
        remove.addEventListener('click', () => { savedItems = savedItems.filter(saved => saved.id !== item.id); writeSaved(); content.querySelector('button, a')?.focus(); });
        row.append(photoLink, body, remove); content.append(row);
    });
}

export function mountSaved() {
    savedItems = readSaved(); updateSaved();
    document.querySelectorAll('[data-save-card]').forEach(button => button.addEventListener('click', () => {
        let item; try { item = validItem(JSON.parse(button.dataset.savedItem)); } catch { return; }
        if (!item) return;
        if (savedItems.some(saved => saved.id === item.id)) savedItems = savedItems.filter(saved => saved.id !== item.id);
        else { if (savedItems.length >= 50) { notify('50 tagacha e’lon saqlash mumkin. Avval bittasini olib tashlang.'); return; } savedItems.unshift(item); }
        writeSaved();
    }));
    const panel = document.querySelector('[data-saved-panel]');
    if (!panel) return;
    const openers = document.querySelectorAll('[data-saved-open]');
    openers.forEach(button => button.addEventListener('click', () => { updateSaved(); panel.hidden = false; panel.showModal(); openers.forEach(opener => opener.setAttribute('aria-expanded', 'true')); }));
    panel.querySelector('[data-saved-close]')?.addEventListener('click', () => panel.close());
    panel.addEventListener('close', () => { panel.hidden = true; openers.forEach(button => button.setAttribute('aria-expanded', 'false')); });
    panel.addEventListener('click', event => { const box = panel.getBoundingClientRect(); if (event.target === panel && (event.clientX < box.left || event.clientX > box.right || event.clientY < box.top || event.clientY > box.bottom)) panel.close(); });
    window.addEventListener('storage', event => { if (event.key === savedKey) { savedItems = readSaved(); updateSaved(); } });
}

export function mountHero(node) {
    const slides = [
        { title: ['O‘zingizga', 'mosini toping'], description: 'Avto, ehtiyot qism va moto — bir joyda', action: 'Avtomobillarni ko‘rish', href: '/elonlar?section=cars' },
        { title: ['Kerakli detal', 'shu yerda'], description: 'Avtomobilingizga mos ehtiyot qismlarni toping', action: 'Ehtiyot qismlarni ko‘rish', href: '/elonlar?section=parts' },
        { title: ['Yangi yo‘llar', 'sizni kutmoqda'], description: 'Mototsikl, skuter va mopedlarni kashf eting', action: 'Moto takliflarini ko‘rish', href: '/elonlar?section=moto' },
        { title: ['Qulay safar', 'shu yerdan'], description: 'Safaringiz uchun mos avtomobilni ijaraga oling', action: 'Arenda takliflarini ko‘rish', href: '/elonlar?section=cars&offer_type=rent' },
    ];
    node.querySelectorAll('[data-hero-slide]').forEach(button => button.addEventListener('click', () => {
        const slide = slides[Number(button.dataset.heroSlide)]; if (!slide) return;
        node.dataset.heroActive = button.dataset.heroSlide;
        node.querySelectorAll('[data-hero-title] span').forEach((span, index) => { span.textContent = slide.title[index]; });
        node.querySelector('[data-hero-description]').textContent = slide.description;
        const action = node.querySelector('[data-hero-action]'); action.href = slide.href; action.setAttribute('aria-label', slide.action); action.querySelector('span').textContent = slide.action;
        node.querySelectorAll('[data-hero-slide]').forEach(dot => { const selected = dot === button; dot.classList.toggle('is-active', selected); dot.setAttribute('aria-pressed', String(selected)); });
    }));
}

export async function mountFinder(form) {
    const make = form.querySelector('[data-fit-make]'), model = form.querySelector('[data-fit-model]'), status = form.querySelector('[data-fit-status]');
    let request = 0;
    function reset(select, label) { select.replaceChildren(new Option(label, '')); select.disabled = true; }
    async function catalog(path, params) {
        const rows = [];
        for (let page = 1; page <= 20; page++) {
            const { data } = await api.get(path, { params: { ...params, section: 'cars', per_page: 100, page } }); rows.push(...data.data);
            if (!data.next_page_url) break;
        }
        return rows;
    }
    make.addEventListener('change', async () => {
        const sequence = ++request; reset(model, make.value ? 'Modellar yuklanmoqda…' : 'Model tanlang'); status.textContent = '';
        if (!make.value) return;
        try {
            const models = await catalog('/catalog/vehicles/models', { vehicle_make_id: make.value });
            if (sequence !== request) return;
            reset(model, models.length ? 'Model tanlang' : 'Model topilmadi'); models.forEach(row => model.add(new Option(row.name, row.id))); model.disabled = !models.length;
            if (!models.length) status.textContent = 'Bu marka uchun katalog hali to‘ldirilmagan.';
        } catch { if (sequence === request) { reset(model, 'Qayta urinib ko‘ring'); status.textContent = 'Modellarni yuklab bo‘lmadi. Markani qayta tanlang.'; } }
    });
    reset(make, 'Markalar yuklanmoqda…');
    try {
        const makes = await catalog('/catalog/vehicles/makes', {}); reset(make, makes.length ? 'Marka tanlang' : 'Marka topilmadi'); makes.forEach(row => make.add(new Option(row.name, row.id))); make.disabled = !makes.length;
        if (!makes.length) status.textContent = 'Transport katalogi to‘ldirilmoqda. Detallarni katalogdan ham ko‘rishingiz mumkin.';
    } catch { reset(make, 'Markalar yuklanmadi'); status.textContent = 'Katalogni yuklab bo‘lmadi. Sahifani yangilang yoki ehtiyot qismlar bo‘limini oching.'; }
    form.addEventListener('submit', event => { if (!make.value || !model.value) { event.preventDefault(); status.textContent = 'Avval avtomobil markasi va modelini tanlang.'; } });
}
