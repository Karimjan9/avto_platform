import { readFileSync, readdirSync, statSync, writeFileSync } from 'node:fs';
import { gzipSync } from 'node:zlib';
import { resolve } from 'node:path';

const root = resolve('public/build');
const manifest = JSON.parse(readFileSync(resolve(root, 'manifest.json'), 'utf8'));
for (const file of readdirSync(resolve(root, 'assets'))) {
    if (/\.(js|css)$/.test(file)) writeFileSync(resolve(root, 'assets', file + '.gz'), gzipSync(readFileSync(resolve(root, 'assets', file)), { level: 9 }));
}
const entries = ['resources/js/app.js', 'resources/css/app.css'];
const visited = new Set();
function visit(key) {
    const chunk = manifest[key];
    if (!chunk) throw new Error(`Missing Vite entry: ${key}`);
    visited.add(chunk.file);
    for (const file of chunk.css || []) visited.add(file);
    for (const imported of chunk.imports || []) visit(imported);
}
entries.forEach(visit);
const files = [...visited].map(file => ({ file, bytes: statSync(resolve(root, file)).size, gzip: gzipSync(readFileSync(resolve(root, file))).length }));
const js = files.filter(item => item.file.endsWith('.js')).reduce((sum, item) => sum + item.gzip, 0);
const css = files.filter(item => item.file.endsWith('.css')).reduce((sum, item) => sum + item.gzip, 0);
if (js > 80 * 1024 || css > 30 * 1024) throw new Error(`Initial payload budget exceeded: JS ${js}, CSS ${css}`);
const forbidden = ['calendar-', 'inventory-', 'listing-editor-', 'status-chart-', 'csv-import-'];
if (files.some(item => forbidden.some(name => item.file.includes(name)))) throw new Error('Page-specific code entered the common bundle.');
console.log(JSON.stringify({ initialJavaScriptGzipBytes: js, initialCssGzipBytes: css, budgets: { js: 81920, css: 30720 }, files }, null, 2));
