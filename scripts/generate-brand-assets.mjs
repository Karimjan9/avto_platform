/**
 * Original BuxoroAvto vector identity: custom B/A paths and a road lane accent.
 * No stock symbols, fonts, or external graphics are used in the icon itself.
 * Run with Node 24: node scripts/generate-brand-assets.mjs
 * Chromium (Playwright) renders the SVG; Roboto is used only on the share card.
 */
import { chromium } from '@playwright/test';
import { createHash } from 'node:crypto';
import { mkdir, readFile, writeFile } from 'node:fs/promises';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const publicDir = resolve(root, 'public');
const previewArgument = process.argv.find(argument => argument.startsWith('--preview-dir='));
const previewDir = resolve(root, previewArgument?.slice('--preview-dir='.length) || '../.tools/branding-preview');
const master = await readFile(resolve(publicDir, 'brand/buxoroavto-mark.svg'), 'utf8');
const glyph = master.match(/<g transform="translate\(-1\.7 0\)">[\s\S]*<\/g>/)?.[0];
if (!glyph || master.includes('<text')) throw new Error('Expected the original, font-independent mark.');

function svgVariant({ fullBleed = false, maskable = false } = {}) {
    const art = maskable
        ? `<g transform="translate(32 32) scale(.82) translate(-32 -32)">${glyph}</g>`
        : glyph;
    return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect width="64" height="64" rx="${fullBleed ? 0 : 15}" fill="#5d3bfa"/>${art}</svg>`;
}

await mkdir(resolve(publicDir, 'brand'), { recursive: true });
await mkdir(previewDir, { recursive: true });
await writeFile(resolve(publicDir, 'favicon.svg'), master);

const browser = await chromium.launch({ headless: true });
const outputs = [];
try {
    const page = await browser.newPage({ viewport: { width: 1200, height: 630 }, deviceScaleFactor: 1 });
    await page.setContent('<!doctype html><html lang="uz"><meta charset="utf-8"><body style="margin:0"><canvas></canvas></body></html>');
    const renderIcon = async (svg, size) => {
        const base64 = await page.evaluate(async ({ svg, size }) => {
            const image = new Image();
            const ready = new Promise((resolve, reject) => { image.onload = resolve; image.onerror = reject; });
            image.src = `data:image/svg+xml;base64,${btoa(svg)}`;
            await ready;
            const canvas = document.querySelector('canvas');
            canvas.width = size;
            canvas.height = size;
            canvas.getContext('2d').drawImage(image, 0, 0, size, size);
            return canvas.toDataURL('image/png').split(',')[1];
        }, { svg, size });
        return Buffer.from(base64, 'base64');
    };
    const pngs = new Map();
    for (const size of [16, 32, 48, 180, 192, 512]) pngs.set(size, await renderIcon(master, size));
    const iconFiles = [
        ['favicon-16x16.png', pngs.get(16)],
        ['favicon-32x32.png', pngs.get(32)],
        ['apple-touch-icon.png', await renderIcon(svgVariant({ fullBleed: true }), 180)],
        ['icon-192.png', pngs.get(192)],
        ['icon-512.png', pngs.get(512)],
        ['icon-maskable-512.png', await renderIcon(svgVariant({ fullBleed: true, maskable: true }), 512)],
    ];
    for (const [name, bytes] of iconFiles) {
        await writeFile(resolve(publicDir, name), bytes);
        outputs.push({ name, bytes: bytes.length });
    }

    // A genuine ICO directory containing three independently rendered PNG frames.
    const sizes = [16, 32, 48];
    const directory = Buffer.alloc(6 + sizes.length * 16);
    directory.writeUInt16LE(1, 2);
    directory.writeUInt16LE(sizes.length, 4);
    let offset = directory.length;
    sizes.forEach((size, index) => {
        const entry = 6 + index * 16;
        const bytes = pngs.get(size);
        directory[entry] = size;
        directory[entry + 1] = size;
        directory.writeUInt16LE(1, entry + 4);
        directory.writeUInt16LE(32, entry + 6);
        directory.writeUInt32LE(bytes.length, entry + 8);
        directory.writeUInt32LE(offset, entry + 12);
        offset += bytes.length;
    });
    const ico = Buffer.concat([directory, ...sizes.map(size => pngs.get(size))]);
    await writeFile(resolve(publicDir, 'favicon.ico'), ico);
    outputs.push({ name: 'favicon.ico', bytes: ico.length, frames: sizes });

    const font = (await readFile(resolve(publicDir, 'fonts/roboto-latin-variable.woff2'))).toString('base64');
    const share = await page.evaluate(async ({ svg, font }) => {
        const face = new FontFace('BuxoroBrand', `url(data:font/woff2;base64,${font})`, { weight: '100 900' });
        await face.load();
        document.fonts.add(face);
        await document.fonts.ready;
        const canvas = document.querySelector('canvas');
        canvas.width = 1200;
        canvas.height = 630;
        const context = canvas.getContext('2d');
        context.fillStyle = '#5d3bfa';
        context.fillRect(0, 0, 1200, 630);

        // Restrained perspective road, kept behind the clear text area.
        context.lineCap = 'round';
        context.strokeStyle = 'rgba(255,255,255,.075)';
        context.lineWidth = 90;
        context.beginPath(); context.moveTo(1280, 720); context.lineTo(1000, -100); context.stroke();
        context.strokeStyle = 'rgba(255,255,255,.11)';
        context.lineWidth = 3;
        context.beginPath(); context.moveTo(1118, 680); context.lineTo(944, -60); context.stroke();
        context.beginPath(); context.moveTo(1368, 680); context.lineTo(1032, -60); context.stroke();
        context.strokeStyle = 'rgba(255,255,255,.24)';
        context.lineWidth = 5;
        context.setLineDash([22, 34]);
        context.beginPath(); context.moveTo(1254, 680); context.lineTo(994, -60); context.stroke();
        context.setLineDash([]);

        const mark = new Image();
        const loaded = new Promise((resolve, reject) => { mark.onload = resolve; mark.onerror = reject; });
        mark.src = `data:image/svg+xml;base64,${btoa(svg)}`;
        await loaded;
        context.fillStyle = 'rgba(255,255,255,.16)';
        context.beginPath(); context.roundRect(68, 66, 110, 110, 27); context.fill();
        context.drawImage(mark, 73, 71, 100, 100);
        context.fillStyle = 'rgba(255,255,255,.82)';
        context.font = '600 22px BuxoroBrand';
        context.fillText('BUXORO AVTO MARKETPLACE', 202, 131);

        context.fillStyle = '#ffffff';
        context.font = '800 100px BuxoroBrand';
        context.fillText('BuxoroAvto', 68, 322);
        context.font = '400 35px BuxoroBrand';
        context.fillStyle = '#f4f1ff';
        context.fillText('Avto, ehtiyot qism va moto — bir joyda', 72, 402);
        context.fillStyle = '#ff661d';
        context.beginPath(); context.roundRect(72, 492, 56, 7, 3.5); context.fill();
        context.font = '500 22px BuxoroBrand';
        context.fillStyle = 'rgba(255,255,255,.84)';
        context.fillText('platform.buxoroavto.uz', 149, 504);
        return canvas.toDataURL('image/png').split(',')[1];
    }, { svg: master, font });
    const shareBytes = Buffer.from(share, 'base64');
    await writeFile(resolve(publicDir, 'brand/buxoroavto-share.png'), shareBytes);
    outputs.push({ name: 'brand/buxoroavto-share.png', bytes: shareBytes.length, width: 1200, height: 630 });

    const board = await page.evaluate(async svg => {
        const canvas = document.querySelector('canvas');
        canvas.width = 1100; canvas.height = 420;
        const context = canvas.getContext('2d');
        context.fillStyle = '#f6f6fa'; context.fillRect(0, 0, 1100, 420);
        context.fillStyle = '#252434'; context.font = '700 26px BuxoroBrand';
        context.fillText('BuxoroAvto · original road monogram', 40, 52);
        context.font = '400 15px BuxoroBrand'; context.fillStyle = '#736d80';
        context.fillText('Final geometry · SVG master, actual display sizes', 40, 82);
        const image = new Image();
        const loaded = new Promise((resolve, reject) => { image.onload = resolve; image.onerror = reject; });
        image.src = `data:image/svg+xml;base64,${btoa(svg)}`; await loaded;
        for (const { x, size } of [{ x: 40, size: 16 }, { x: 215, size: 32 }, { x: 390, size: 41 }, { x: 650, size: 180 }]) {
            const width = size > 100 ? 210 : 150;
            context.fillStyle = '#fff'; context.fillRect(x, 112, width, 245);
            context.drawImage(image, x + (width - size) / 2, 146, size, size);
            context.fillStyle = '#252434'; context.font = '700 17px BuxoroBrand'; context.fillText(`${size} px`, x + 18, 338);
        }
        context.fillStyle = '#5d3bfa'; context.font = '700 25px BuxoroBrand';
        context.fillText('BA / converging road', 40, 390);
        return canvas.toDataURL('image/png').split(',')[1];
    }, master);
    await writeFile(resolve(previewDir, 'buxoroavto-icons-preview.png'), Buffer.from(board, 'base64'));
} finally {
    await browser.close();
}

const manifest = {
    id: '/',
    name: 'BuxoroAvto',
    short_name: 'BuxoroAvto',
    description: 'Avto, ehtiyot qism va moto — bir joyda',
    lang: 'uz',
    start_url: '/',
    scope: '/',
    display: 'browser',
    background_color: '#5d3bfa',
    theme_color: '#5d3bfa',
    icons: [
        { src: '/icon-192.png?v=20261024b', sizes: '192x192', type: 'image/png', purpose: 'any' },
        { src: '/icon-512.png?v=20261024b', sizes: '512x512', type: 'image/png', purpose: 'any' },
        { src: '/icon-maskable-512.png?v=20261024b', sizes: '512x512', type: 'image/png', purpose: 'maskable' },
    ],
};
await writeFile(resolve(publicDir, 'site.webmanifest'), JSON.stringify(manifest, null, 2) + '\n');
console.log(JSON.stringify({
    master: 'public/brand/buxoroavto-mark.svg',
    sha256: createHash('sha256').update(master).digest('hex'),
    outputs,
    preview: resolve(previewDir, 'buxoroavto-icons-preview.png'),
}, null, 2));
