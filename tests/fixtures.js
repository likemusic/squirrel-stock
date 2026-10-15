// Общие фикстуры: внешние запросы блокируются (программа должна работать без интернета),
// диалоги confirm() принимаются, ошибки JS на странице копятся в pageErrors.
const base = require('@playwright/test');
const { blockExternal } = require('./helpers/zippy');

const test = base.test.extend({
  context: async ({ context }, use) => {
    await blockExternal(context);
    await use(context);
  },
  page: async ({ page }, use) => {
    page.on('dialog', d => d.accept());
    await use(page);
  },
  // уникальный суффикс: тесты не зависят от того, что уже есть в базе стенда
  uniq: async ({}, use) => {
    await use(Date.now().toString(36).toUpperCase());
  },
});

module.exports = { test, expect: base.expect };
