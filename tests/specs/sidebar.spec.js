// Боковое меню (TODO 6.1, 6.2). Булавка закрепляет меню: закрепленное остается открытым после перехода на другую страницу,
// состояние хранится у пользователя — это та же настройка, что галочка «Скрывать боковое меню» в профиле (collapsesidebar,
// закреплено = 0). На телефоне меню по-прежнему выезжает поверх страницы и прячется, булавки там нет. При открытии страницы
// в меню раскрыт путь к ее пункту (раздел → группа → пункт), сам пункт подсвечен.
const { test, expect } = require('../fixtures');
const { value } = require('../helpers/db');

const collapseOption = () => value(`select options from users where userlogin = 'admin'`).match(/s:15:"collapsesidebar";i:(\d)/)[1];
// закрепить (1) или открепить (0) меню так же, как это делает булавка; страница должна быть открыта
const setPinned = (page, on) => page.evaluate(v => new Promise(res => callPM('sqsPinSidebar', [v], null, res, res)), on ? 1 : 0);
const goto = async (page, url) => {
  await page.goto(url);
  await expect(page.locator('#sidebar')).toBeAttached();
};

test.describe('закрепление меню', () => {
  let was;
  test.beforeEach(async ({ page }) => {
    was = collapseOption();
    await goto(page, '/index.php?p=App/Pages/Register/DocList');
    await setPinned(page, false);
  });
  test.afterEach(async ({ page }) => {
    await goto(page, '/index.php?p=App/Pages/Register/DocList');
    await setPinned(page, was === '0');
  });

  test('булавка: закрепленное меню открыто после перехода, откреплённое свернуто; галочка в профиле та же', async ({ page }) => {
    await goto(page, '/index.php?p=App/Pages/Register/DocList');
    const side = page.locator('#sidebar'), pin = page.locator('#sqspin');
    await expect(side).toHaveClass(/collapsed/);
    await page.locator('#barbtn').click();
    await expect(side).not.toHaveClass(/collapsed/);
    await expect(pin).toBeVisible();
    await expect(pin).toHaveAttribute('aria-pressed', 'false');

    await Promise.all([page.waitForResponse(r => r.url().includes('sqsPinSidebar')), pin.click()]);
    await expect(pin).toHaveAttribute('aria-pressed', 'true');
    expect(collapseOption(), 'сохранено у пользователя сразу, без перезагрузки').toBe('0');

    await goto(page, '/index.php?p=App/Pages/Reference/ItemList');
    await expect(side, 'закрепленное меню открыто на другой странице').not.toHaveClass(/collapsed/);
    await expect(pin).toHaveAttribute('aria-pressed', 'true');

    await goto(page, '/index.php?p=App/Pages/UserProfile');
    const box = page.locator('input[type=checkbox]#collapsesidebar');
    await expect(box, 'в профиле галочка «Скрывать» снята').not.toBeChecked();
    await Promise.all([page.waitForResponse(r => r.url().includes('sqsPinSidebar')), pin.click()]);
    await expect(box, 'булавка в профиле сразу переставляет галочку').toBeChecked();
    expect(collapseOption()).toBe('1');

    await goto(page, '/index.php?p=App/Pages/Reference/ItemList');
    await expect(side, 'откреплённое меню свернуто после перехода').toHaveClass(/collapsed/);
  });

  test('булавка принимает только 0 и 1', async ({ page }) => {
    await page.evaluate(() => new Promise(res => callPM('sqsPinSidebar', ['abc'], null, res, res)));
    expect(collapseOption()).toBe('1');
  });

  test.describe('телефон', () => {
    test.use({ viewport: { width: 375, height: 667 }, isMobile: true, hasTouch: true });

    test('закрепленное меню на телефоне свернуто, булавки нет, меню открывается и закрывается как раньше', async ({ page }) => {
      await setPinned(page, true);
      await goto(page, '/index.php?p=App/Pages/Reference/ItemList');
      const side = page.locator('#sidebar');
      await expect(side).toHaveClass(/collapsed/);
      await page.locator('#barbtn').tap();
      await expect(side).not.toHaveClass(/collapsed/);
      await expect(page.locator('#sqspin')).toBeHidden();
      await page.locator('#barbtn').tap();
      await expect(side).toHaveClass(/collapsed/);
    });
  });
});

test.describe('путь к текущей странице', () => {
  // раскрытые разделы и группы меню и подсвеченный пункт
  const state = page => page.locator('#sidebar').evaluate(s => ({
    open: [...s.querySelectorAll('ul.collapse.show')].map(u => u.id),
    active: [...s.querySelectorAll('a.sidebar-link.active')].map(a => a.getAttribute('href')),
    headers: [...s.querySelectorAll('a[data-bs-toggle=collapse]:not(.collapsed)')].map(a => a.dataset.bsTarget),
  }));

  test('пункт без группы: раскрыт только раздел, пункт подсвечен', async ({ page }) => {
    await goto(page, '/index.php?p=App/Pages/Register/DocList');
    const s = await state(page);
    expect(s.active).toEqual(['/index.php?p=App/Pages/Register/DocList']);
    expect(s.open).toEqual(['mnregs']);
    expect(s.headers).toEqual(['#mnregs']);
    await expect(page.locator('#sidebar a.sidebar-link.active')).toHaveAttribute('aria-current', 'page');
  });

  test('отчет в группе: раскрыты раздел и группа', async ({ page }) => {
    await goto(page, '/index.php?p=App/Pages/Report/IssueAct');
    const s = await state(page);
    expect(s.active).toEqual(['/index.php?p=App/Pages/Report/IssueAct']);
    expect(s.open[0]).toBe('mnreps');
    expect(s.open).toHaveLength(2);
    expect(s.headers).toEqual(['#mnreps', '#' + s.open[1]]);
  });

  test('документ: подсвечен его пункт в разделе документов', async ({ page }) => {
    await goto(page, '/index.php?p=App/Pages/Doc/GoodsIssue');
    const s = await state(page);
    expect(s.active).toEqual(['/index.php?p=App/Pages/Doc/GoodsIssue']);
    expect(s.open[0]).toBe('mndocs');
  });

  test('страницы нет в меню (профиль) — ничего не раскрыто и не подсвечено', async ({ page }) => {
    await goto(page, '/index.php?p=App/Pages/UserProfile');
    const s = await state(page);
    expect(s.active).toEqual([]);
    expect(s.open).toEqual([]);
  });

  test('подсветка пункта видна в светлой и темной теме', async ({ page }) => {
    await goto(page, '/index.php?p=App/Pages/Reference/ItemList');
    for (const theme of ['light', 'dark']) {
      await page.evaluate(t => { document.documentElement.dataset.bsTheme = t; document.body.dataset.bsTheme = t; }, theme);
      const [act, other] = await page.locator('#mnrefs a.sidebar-link').evaluateAll(as => {
        const a = as.find(x => x.classList.contains('active')), o = as.find(x => !x.classList.contains('active'));
        return [a, o].map(x => { const c = getComputedStyle(x); return c.backgroundColor + '|' + c.borderLeftColor + '|' + c.borderLeftWidth; });
      });
      expect(act, `тема ${theme}: пункт отличается от соседнего`).not.toBe(other);
    }
  });
});
