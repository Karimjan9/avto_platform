import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
    testDir: './tests/Browser', timeout: 45000, expect: { timeout: 10000 }, fullyParallel: false, workers: 1,
    reporter: [['list'], ['html', { open: 'never' }]],
    use: { baseURL: process.env.PLAYWRIGHT_BASE_URL || 'http://127.0.0.1:8012', trace: 'retain-on-failure', screenshot: 'only-on-failure' },
    projects: [{ name: 'desktop', use: { ...devices['Desktop Chrome'] } }, { name: 'mobile', use: { ...devices['Pixel 7'] } }],
});
