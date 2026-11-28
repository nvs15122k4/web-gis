// Chụp thêm: so sánh năm (06), hướng dẫn (08 a/b), quản trị ngưỡng (12). Chạy: node shot-readme2.cjs <thư mục ra>
const { chromium } = require('@playwright/test');
const fs = require('fs'), path = require('path');
const out = process.argv[2]; const BASE = process.env.BASE || 'http://127.0.0.1:8000';
const env = Object.fromEntries(fs.readFileSync(path.resolve(__dirname, '../web/.env'), 'utf8').split('\n').filter((l) => /^DEMO_ADMIN_/.test(l)).map((l) => l.split('=')));
fs.mkdirSync(out, { recursive: true });
(async () => {
    const b = await chromium.launch();
    const ctx = await b.newContext({ viewport: { width: 1440, height: 900 }, locale: 'vi-VN' });
    const p = await ctx.newPage();
    const shot = async (n, w = 2500) => { await p.waitForTimeout(w); await p.screenshot({ path: path.join(out, n) }); console.log('ok', n); };
    await p.goto(BASE + '/', { waitUntil: 'networkidle' });
    await p.getByText('Đã hiểu').click({ timeout: 8000 }).catch(() => {});
    await p.getByRole('tab', { name: /Lớp dữ liệu/ }).click();
    await p.locator('#compare-a').selectOption('2018'); await p.locator('#compare-b').selectOption('2026');
    await p.locator('#compare-apply').scrollIntoViewIfNeeded(); await p.locator('#compare-apply').click();
    await shot('06_so_sanh_nam.png', 4000);
    await p.goto(BASE + '/huong-dan', { waitUntil: 'networkidle' }); await shot('08_huong_dan_a.png', 1500);
    await p.evaluate(() => window.scrollTo(0, 1800)); await shot('08_huong_dan_b.png', 1500);
    await p.goto(BASE + '/dang-nhap', { waitUntil: 'networkidle' });
    await p.locator('input[type=email], input[name=email]').first().fill(env.DEMO_ADMIN_EMAIL);
    await p.locator('input[type=password]').first().fill(env.DEMO_ADMIN_PASSWORD);
    await p.locator('button[type=submit]').first().click(); await p.waitForLoadState('networkidle');
    await p.goto(BASE + '/admin', { waitUntil: 'networkidle' }); await shot('12_quan_tri_nguong.png', 2500);
    await b.close();
})().catch((e) => { console.error(e); process.exit(1); });
