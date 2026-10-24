import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { createHash } from 'node:crypto';

const diagnostics = new WeakMap();

async function selectListingSection(page, section) {
    const radio = page.locator(`[data-listing-section][value="${section}"]`);
    await radio.locator('..').click();
    await expect(radio).toBeChecked();
}

test.beforeEach(async ({ page }) => {
    const state = { errors: [], network: [] };
    diagnostics.set(page, state);
    page.on('pageerror', error => state.errors.push(error.message));
    page.on('requestfailed', request => {
        const failure = request.failure()?.errorText || '';
        if (!failure.includes('ERR_ABORTED')) state.network.push(`${failure} ${request.url()}`);
    });
    page.on('response', response => { if (response.status() >= 400) state.network.push(`${response.status()} ${response.url()}`); });
});
test.afterEach(async ({ page }, testInfo) => {
    const state = diagnostics.get(page);
    if (state.errors.length || state.network.length) await testInfo.attach('browser-diagnostics', { body: JSON.stringify(state, null, 2), contentType: 'application/json' });
    expect(state.errors, 'Unhandled browser JavaScript errors').toEqual([]);
    expect(state.network, 'Failed network requests (excluding intentional navigation/request aborts)').toEqual([]);
});

test('marketplace is accessible, mobile layout fits, and heavy widgets stay unloaded', async ({ page }, testInfo) => {
    test.setTimeout(90000);
    const errors = [], scripts = [];
    page.on('pageerror', error => errors.push(error.message));
    page.on('request', request => { if (request.resourceType() === 'script') scripts.push(request.url()); });
    await page.goto('/');
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
    await expect(page.locator('[data-fit-make]')).toBeEnabled();
    const widths = testInfo.project.name === 'mobile' ? [412] : [1440, 1586];
    for (const width of widths) {
        await page.setViewportSize({ width, height: width === 412 ? 915 : 1000 });
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBeTruthy();
        await expect.poll(() => page.locator('.market-hero-image').evaluate(image => image.naturalWidth)).toBeGreaterThan(0);
        await page.screenshot({ path: testInfo.outputPath(`home-${width}.png`), fullPage: true, scale: 'css' });
    }
    expect(scripts.some(url => /inventory-|listing-editor-|calendar-|status-chart-|csv-import-/.test(url))).toBeFalsy();
    const scan = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa']).analyze();
    expect(scan.violations.filter(item => ['critical', 'serious'].includes(item.impact))).toEqual([]);
    await page.goto('/elonlar');
    await page.getByRole('button', { name: 'Narx shkalasi' }).click();
    await expect(page.locator('.noUi-target')).toBeVisible();
    expect(errors).toEqual([]);
});

test('cars and new condition filters can be selected and submitted', async ({ page }) => {
    await page.goto('/elonlar');
    const section = page.locator('select[name="section"]');
    const condition = page.locator('select[name="condition"]');
    await expect(section.locator('option[value="cars"]')).toHaveText('Avtomobillar');
    await expect(condition.locator('option[value="new"]')).toHaveText('Yangi');
    await section.selectOption('cars');
    await condition.selectOption('new');
    await page.locator('[data-filter-form]').getByRole('button', { name: 'Qidirish', exact: true }).click();
    await expect(page).toHaveURL(url => url.pathname === '/elonlar' && url.searchParams.get('section') === 'cars' && url.searchParams.get('condition') === 'new');
    await expect(section).toHaveValue('cars');
    await expect(condition).toHaveValue('new');
    await expect(page.getByRole('heading', { level: 1, name: 'Avtomobillar', exact: true })).toBeVisible();
});

test('hero banners, categories and make model year finder reach real compatible shop offers', async ({ page, request }) => {
    test.setTimeout(90000);
    const productsResponse = await request.get('/api/v1/parts');
    expect(productsResponse.ok()).toBeTruthy();
    const products = (await productsResponse.json()).data;
    const product = products.find(item => item.offers_count > 0);
    expect(product, 'Prepare an isolated active part product, vehicle fitment and published shop offer').toBeTruthy();
    const detailResponse = await request.get(`/api/v1/parts/${product.id}`);
    expect(detailResponse.ok()).toBeTruthy();
    const detail = (await detailResponse.json()).data;
    const fitment = detail.fitments.find(item => item.variant.generation.model.section === 'cars');
    expect(fitment).toBeTruthy();
    const variant = fitment.variant, modelData = variant.generation.model;
    const year = Math.max(fitment.year_from || 1900, variant.generation.year_from);

    await page.goto('/');
    await expect(page.locator('.market-quick-categories a')).toHaveCount(6);
    for (const link of await page.locator('.market-quick-categories a').all()) expect(new URL(await link.getAttribute('href')).pathname).toBe('/elonlar');
    await page.getByRole('button', { name: 'Ehtiyot qismlar banneri', exact: true }).click();
    await expect(page.locator('[data-hero-title]')).toHaveText('Kerakli detalshu yerda');
    await expect(page.locator('[data-hero-action]')).toHaveAttribute('href', '/elonlar?section=parts');
    await page.getByRole('button', { name: 'Moto banneri', exact: true }).click();
    await expect(page.locator('[data-hero-action]')).toHaveAttribute('href', '/elonlar?section=moto');

    const make = page.locator('[data-fit-make]'), model = page.locator('[data-fit-model]');
    await expect(make).toBeEnabled();
    await make.selectOption(String(modelData.vehicle_make_id));
    await expect(model).toBeEnabled();
    await model.selectOption(String(modelData.id));
    const otherMake = await make.locator('option').evaluateAll((options, selected) => options.find(option => option.value && option.value !== selected)?.value, String(modelData.vehicle_make_id));
    expect(otherMake, 'A second catalog make exercises stale model reset').toBeTruthy();
    await make.selectOption(otherMake);
    await expect(model).toBeEnabled();
    await expect(model).toHaveValue('');
    await expect(model.locator(`option[value="${modelData.id}"]`)).toHaveCount(0);
    await make.selectOption(String(modelData.vehicle_make_id));
    await expect(model).toBeEnabled();
    await model.selectOption(String(modelData.id));
    await page.locator('[data-fit-finder] select[name="year"]').selectOption(String(year));
    await page.getByRole('button', { name: 'Mos detallarni topish', exact: true }).click();
    await expect(page).toHaveURL(url => url.pathname === '/mos-detallar' && url.searchParams.get('vehicle_model_id') === String(modelData.id) && url.searchParams.get('year') === String(year));
    const result = page.locator(`[data-fitment-product="${product.id}"]`);
    await expect(result.getByRole('heading', { name: product.title, exact: true })).toBeVisible();
    await result.getByRole('link', { name: 'Do‘kon takliflarini ko‘rish', exact: true }).click();
    await expect(page).toHaveURL(url => url.pathname === '/elonlar' && url.searchParams.get('section') === 'parts' && url.searchParams.get('part_product_id') === String(product.id));
    await expect(page.locator('.listing-tile')).not.toHaveCount(0);
});

test('header search and saved listings persist across reload and can be removed from the drawer', async ({ page }) => {
    await page.goto('/');
    const item = JSON.parse(await page.locator('[data-save-card]').first().getAttribute('data-saved-item'));
    await page.locator('.site-search input').fill(item.title);
    await page.locator('.site-search button[type="submit"]').click();
    await expect(page).toHaveURL(url => url.pathname === '/elonlar' && url.searchParams.get('q') === item.title);
    const tile = page.locator('.listing-tile').filter({ has: page.getByRole('heading', { level: 3, name: item.title, exact: true }) });
    await tile.locator('[data-save-card]').click();
    await expect(tile.locator('[data-save-card]')).toHaveAttribute('aria-pressed', 'true');
    await page.reload();
    await expect(tile.locator('[data-save-card]')).toHaveAttribute('aria-pressed', 'true');
    const menu = page.getByRole('button', { name: 'Menyuni ochish', exact: true });
    if (await menu.isVisible()) await menu.click();
    await page.locator('[data-saved-open]:visible').first().click();
    const drawer = page.locator('[data-saved-panel]');
    await expect(drawer).toBeVisible();
    await expect(drawer.locator('.site-saved-item-title')).toHaveText(item.title);
    await expect(drawer.locator('.site-saved-item-title')).toHaveAttribute('href', item.url);
    await drawer.locator('.site-saved-remove').click();
    await expect(drawer.locator('.site-saved-item')).toHaveCount(0);
    await expect(drawer.getByRole('heading', { name: 'Hozircha saqlangan e’lon yo‘q', exact: true })).toBeVisible();
    await drawer.locator('[data-saved-close]').click();
    await expect(drawer).toBeHidden();
    await expect(tile.locator('[data-save-card]')).toHaveAttribute('aria-pressed', 'false');
});

test('design previews show only the original responsive image', async ({ page }, testInfo) => {
    const originals = [
        { route: '/1', file: '/design-previews/red.png', sha256: 'ec690167acf9511d49b951558a7c83bf4f30dbbd27c502516b6ee8d825109c87' },
        { route: '/2', file: '/design-previews/yellow.png', sha256: '2a977cc55b2a908f2073d0cbce4f87902f6b3c9665213f7afee0e0cf8be8a71d' },
    ];
    for (const original of originals) {
        await page.goto(original.route);
        await expect(page.locator('body > *')).toHaveCount(1);
        const image = page.locator('body > img');
        await expect(image).toBeVisible();
        await expect(page.locator('script, header, nav, form, button')).toHaveCount(0);
        const source = await image.getAttribute('src');
        expect(new URL(source).pathname).toBe(original.file);
        const response = await page.request.get(source);
        expect(response.ok()).toBeTruthy();
        expect(createHash('sha256').update(await response.body()).digest('hex')).toBe(original.sha256);
        await expect.poll(() => image.evaluate(node => node.naturalWidth)).toBeGreaterThan(0);
        expect(await image.evaluate(node => Math.abs(node.getBoundingClientRect().width - document.documentElement.clientWidth) < 1)).toBeTruthy();
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBeTruthy();
        await page.screenshot({ path: testInfo.outputPath(`preview-${original.route.slice(1)}-${testInfo.project.name}.png`), fullPage: true, scale: 'css' });
    }
});

test('email registration requires phone verification and verified sellers can draft and upload images', async ({ page }) => {
    test.setTimeout(150000);
    const errors = []; page.on('pageerror', error => errors.push(error.message));
    await page.goto('/royxatdan-otish');
    await page.getByLabel('Ismingiz').fill('Frontend sinovchi');
    await page.getByLabel('Email', { exact: true }).fill(`browser-${Date.now()}@avto.test`);
    await page.getByLabel('Parol', { exact: true }).fill('BrowserPass123');
    await page.getByLabel('Parolni takrorlang').fill('BrowserPass123');
    await page.getByRole('button', { name: 'Akkaunt yaratish' }).click();
    await expect(page).toHaveURL('/kabinet');
    await expect(page.getByRole('heading', { name: 'Telefon raqamingizni tasdiqlang', exact: true })).toBeVisible();
    await page.goto('/kabinet/elonlar/yangi');
    await expect(page).toHaveURL('/telefonni-tasdiqlash');
    await page.goto('/kabinet');
    await page.getByRole('button', { name: 'Chiqish', exact: true }).click();

    // Editor coverage uses the isolated, verified seller fixture; SMS flows have separate coverage.
    await page.goto('/kirish/email');
    await page.getByLabel('Email', { exact: true }).fill('frontend-owner@avto.test');
    await page.getByLabel('Parol', { exact: true }).fill('BrowserPass123');
    await page.getByRole('button', { name: 'Kirish', exact: true }).click();
    await page.goto('/kabinet/elonlar/yangi');
    await expect(page.locator('[data-path="title"]')).toBeVisible();
    await selectListingSection(page, 'parts');
    await expect(page.locator('[data-path="details.stock_quantity"]')).toBeVisible();
    await page.locator('[data-path="title"]').fill('Brauzer sinovi uchun tormoz kolodkasi');
    await page.locator('[data-path="description"]').fill('Bu mahsulot faqat izolyatsiyalangan brauzer sinovida yaratilgan.');
    await page.locator('[data-path="price"]').fill('150000');
    const contact = page.locator('[data-path="contact_phone"]');
    const verifiedPhone = await page.locator('[data-listing-editor]').getAttribute('data-verified-phone');
    expect(verifiedPhone, 'The isolated editor fixture must have a verified Uzbek phone').toMatch(/^\+998[0-9]{9}$/);
    await expect(contact).toHaveJSProperty('readOnly', true);
    expect((await contact.inputValue()).replace(/\D/g, '')).toBe(verifiedPhone.slice(1));
    await page.locator('[data-path="details.stock_quantity"]').fill('7');
    await expect(page.locator('[data-submit-moderation]')).toBeChecked();
    await page.locator('[data-submit-moderation]').uncheck();
    await page.locator('input.filepond--browser').setInputFiles({ name: 'sinov.png', mimeType: 'image/png', buffer: await page.screenshot({ type: 'png' }) });
    await expect(page.locator('.filepond--item')).toHaveCount(1);
    const upload = page.waitForResponse(response => response.request().method() === 'POST' && /\/api\/v1\/me\/listings\/\d+\/images$/.test(new URL(response.url()).pathname), { timeout: 120000 });
    await page.getByRole('button', { name: 'Saqlash va davom etish', exact: true }).click();
    expect((await upload).status()).toBe(201);
    await expect(page.locator('[data-save-status]')).toHaveText('Qoralama saqlandi.');
    await expect(page.locator('[data-existing-images] img')).toHaveCount(1);
    await page.reload();
    await expect(page.locator('[data-path="title"]')).toHaveValue('Brauzer sinovi uchun tormoz kolodkasi');
    expect(errors).toEqual([]);
});

test('vehicle form changes between electric car, rental and electric moto', async ({ page }) => {
    await page.goto('/kirish/email');
    await page.getByLabel('Email', { exact: true }).fill('frontend-owner@avto.test');
    await page.getByLabel('Parol', { exact: true }).fill('BrowserPass123');
    await page.getByRole('button', { name: 'Kirish', exact: true }).click();
    await page.goto('/kabinet/elonlar/yangi');
    await page.locator('[data-path="details.fuel_type"]').selectOption('electric');
    await expect(page.locator('[data-path="details.battery_capacity_kwh"]')).toBeVisible();
    await expect(page.locator('[data-path="details.engine_cc"]')).toHaveCount(0);
    await page.locator('[data-path="offer_type"]').selectOption('rent');
    await expect(page.locator('[data-path="rental.deposit"]')).toBeVisible();
    await selectListingSection(page, 'moto');
    await expect(page.locator('[data-path="rental.deposit"]')).toHaveCount(0);
    await page.locator('[data-path="details.fuel_type"]').selectOption('electric');
    await expect(page.locator('[data-path="details.battery_voltage_v"]')).toBeVisible();
    await expect(page.locator('[data-path="details.battery_capacity_kwh"]')).toHaveCount(0);
});

test('business table, CSV preview and rental calendar load their own modules', async ({ page }, testInfo) => {
    const errors = []; page.on('pageerror', error => errors.push(error.message));
    await page.goto('/kirish/email');
    await page.getByLabel('Email', { exact: true }).fill('frontend-owner@avto.test');
    await page.getByLabel('Parol', { exact: true }).fill('BrowserPass123');
    await page.getByRole('button', { name: 'Kirish', exact: true }).click();
    await page.getByRole('link', { name: /Sinov ehtiyot qismlari/ }).click();
    await expect(page.locator('.tabulator-row')).not.toHaveCount(0);
    const stock = page.locator('.tabulator-row .tabulator-cell[tabulator-field="stock"]').first();
    await stock.dblclick();
    await stock.locator('input').fill('12');
    await stock.locator('input').press('Enter');
    await expect(stock).toHaveText('12');
    await page.locator('[data-csv-file]').setInputFiles({ name: 'sinov.csv', mimeType: 'text/csv', buffer: Buffer.from('title,description,price,currency,city,contact_phone,details.stock_quantity\nCSV sinov mahsuloti,Brauzer orqali tekshirilgan ehtiyot qism,100000,UZS,Buxoro,+998901112233,5') });
    await expect(page.locator('[data-csv-preview]')).toContainText('CSV sinov mahsuloti');
    await page.locator('[data-csv-save]').click();
    await expect(page.locator('[data-csv-status]')).toContainText('1 ta mahsulot qoralama sifatida saqlandi');
    await page.goto('/kabinet');
    await page.getByRole('link', { name: 'Bandlik kalendari' }).first().click();
    await expect(page.locator('[data-calendar-status]')).toContainText('faol bandlov');
    await expect(page.locator('[data-calendar-view]')).toContainText('Bugun');
    await expect(page.locator('[data-calendar-view]').getByRole('heading', { level: 2 })).toContainText(/Yanvar|Fevral|Mart|Aprel|May|Iyun|Iyul|Avgust|Sentabr|Oktabr|Noyabr|Dekabr/);
    await page.screenshot({ path: `test-results/calendar-${testInfo.project.name}.png`, fullPage: true });
    expect(errors).toEqual([]);
});

test('listing gallery zoom and rental availability work on the public page', async ({ page }) => {
    const errors = []; page.on('pageerror', error => errors.push(error.message));
    await page.goto('/elonlar?section=cars&offer_type=rent');
    await page.getByRole('link', { name: 'Sinov arenda avtomobili', exact: true }).first().click();
    await expect(page.locator('[data-gallery] img').first()).toBeVisible();
    await expect.poll(() => page.locator('[data-gallery] img').first().evaluate(img => img.naturalWidth)).toBeGreaterThan(0);
    await page.locator('[data-gallery] a').first().focus();
    await page.locator('[data-gallery] a').first().press('Enter');
    await expect(page.locator('.pswp--open')).toBeVisible();
    await expect(page.locator('.pswp')).toBeFocused();
    await page.keyboard.press('Escape');
    await expect(page.locator('.pswp--open')).toHaveCount(0);
    const start = new Date(Date.now() + 6 * 86400000).toISOString().slice(0, 10), end = new Date(Date.now() + 7 * 86400000).toISOString().slice(0, 10);
    await page.locator('[data-rental-start]').fill(start);
    await page.locator('[data-rental-start]').press('Tab');
    await page.locator('[data-rental-end]').fill(end);
    await page.locator('[data-rental-end]').press('Tab');
    await expect(page.locator('[data-availability]')).toContainText('hozircha bo‘sh');
    expect(errors).toEqual([]);
});
