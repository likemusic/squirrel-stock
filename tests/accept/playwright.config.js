// Приемка пакета (tools/release/accept.sh): сценарий показа на копии хостинга, поднятой из пакета. В npm test не входит.
// Запускает accept.sh: npx playwright test --config accept/playwright.config.js (SQS_BASE, SQS_PASS, SQS_SKLAD_PASS, SQS_WEB_CONTAINER)
const { defineConfig } = require('@playwright/test');

module.exports = defineConfig({
  testDir: '.',
  workers: 1,
  fullyParallel: false,
  timeout: 300_000,
  expect: { timeout: 10_000 },
  outputDir: '../test-results/accept',
  reporter: [['list'], ['html', { open: 'never', outputFolder: '../playwright-report/accept' }]],
  use: {
    baseURL: process.env.SQS_BASE,
    viewport: { width: 1440, height: 900 },
    locale: 'ru-RU',
    colorScheme: 'light',
    screenshot: 'only-on-failure',
    trace: 'retain-on-failure',
  },
});
