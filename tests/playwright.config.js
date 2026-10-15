// Регрессионные тесты SquirrelStock. Стенд: cd ../docker && docker compose up -d --build
// Запуск: npm test (другой адрес стенда: SQS_BASE=http://127.0.0.1:8083 npm test)
const { defineConfig } = require('@playwright/test');

module.exports = defineConfig({
  testDir: './specs',
  // Zippy хранит состояние страниц в сессии PHP: параллельные тесты в одной сессии мешают друг другу
  workers: 1,
  fullyParallel: false,
  timeout: 120_000,
  expect: { timeout: 10_000 },
  reporter: [['list'], ['html', { open: 'never' }]],
  globalSetup: require.resolve('./global-setup'),
  use: {
    baseURL: process.env.SQS_BASE || 'http://127.0.0.1:8083',
    storageState: '.auth/admin.json',
    viewport: { width: 1440, height: 900 },
    locale: 'ru-RU',
    screenshot: 'only-on-failure',
    trace: 'retain-on-failure',
  },
});
