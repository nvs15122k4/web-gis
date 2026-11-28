// Chụp lại ảnh giao diện cho README/minh chứng trên dữ liệu QGIS hiện hành (chạy: node shot-readme.cjs <thư mục ra>)
const { chromium } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

const out = process.argv[2];
const BASE = process.env.BASE || 'http://127.0.0.1:8000';
const env = Object.fromEntries(fs.readFileSync(path.resolve(__dirname, '../web/.env'), 'utf8').split('\n')
    .filter((l) => /^DEMO_ADMIN_/.test(l)).map((l) => l.split('=')));
fs.mkdirSync(out, { recursive: true });

(async () => {
    const browser = await chromium.launch();
    const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 }, locale: 'vi-VN' });
    const p = await ctx.newPage();
    const shot = async (name, opt = {}) => { await p.waitForTimeout(opt.wait ?? 2500); await p.screenshot({ path: path.join(out, name), ...opt.s }); console.log('ok', name); };

    await p.goto(BASE + '/', { waitUntil: 'networkidle' });
    await p.getByText('Đã hiểu').click({ timeout: 8000 }).catch(() => {});
    await shot('01_ban_do_tong_quan.png', { wait: 4000 });
    await p.getByRole('tab', { name: /Lớp dữ liệu/ }).click().catch(() => p.getByText('Lớp dữ liệu', { exact: true }).first().click());
    await shot('02_tab_lop.png');
    await p.getByRole('tab', { name: /Hotspot/ }).click().catch(() => p.getByText('Hotspot', { exact: true }).first().click());
    await shot('03_tab_diem_nong.png');
    await p.getByRole('tab', { name: /Chia sẻ/ }).click().catch(() => p.getByText('Chia sẻ', { exact: true }).first().click());
    await shot('04_tab_chia_se.png');
    // chi tiết transect: đoạn lùi mạnh nhất
    const c2 = await browser.newContext({ viewport: { width: 1440, height: 900 }, locale: 'vi-VN' });
    const p2 = await c2.newPage();
    await p2.goto(BASE + '/', { waitUntil: 'networkidle' });
    await p2.getByText('Đã hiểu').click({ timeout: 8000 }).catch(() => {});
    await p2.getByRole('tab', { name: /Lớp dữ liệu/ }).click().catch(() => {});
    const box = p2.getByPlaceholder(/transect|toạ độ|Tìm/i).first();
    await box.fill('T0089');
    await box.press('Enter');
    await p2.waitForTimeout(5000);
    await p2.screenshot({ path: path.join(out, '05_chi_tiet_transect.png') }); console.log('ok 05');

    await p.goto(BASE + '/gioi-thieu', { waitUntil: 'networkidle' });
    await shot('07_gioi_thieu_a.png', { wait: 1500 });
    await p.evaluate(() => window.scrollTo(0, 1500)); await shot('07_gioi_thieu_b.png', { wait: 1500 });
    await p.evaluate(() => window.scrollTo(0, 4200)); await shot('07_gioi_thieu_c.png', { wait: 1500 });
    await p.evaluate(() => document.getElementById('han-che')?.scrollIntoView()); await shot('07_gioi_thieu_d.png', { wait: 1500 });

    // báo cáo: cần đăng nhập admin
    await p.goto(BASE + '/dang-nhap', { waitUntil: 'networkidle' });
    await p.locator('input[type=email], input[name=email]').first().fill(env.DEMO_ADMIN_EMAIL);
    await p.locator('input[type=password]').first().fill(env.DEMO_ADMIN_PASSWORD);
    await p.locator('button[type=submit]').first().click();
    await p.waitForLoadState('networkidle');
    await p.goto(BASE + '/bao-cao', { waitUntil: 'networkidle' });
    await shot('11_bao_cao_a.png', { wait: 1500 });
    await p.evaluate(() => document.getElementById('doi-chieu')?.scrollIntoView()); await shot('11_bao_cao_b.png', { wait: 1500 });

    // di động
    const m = await browser.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true, locale: 'vi-VN' });
    const q = await m.newPage();
    await q.goto(BASE + '/', { waitUntil: 'networkidle' });
    await q.getByText('Đã hiểu').click({ timeout: 8000 }).catch(() => {});
    await q.waitForTimeout(4000);
    await q.screenshot({ path: path.join(out, '13_di_dong.png') }); console.log('ok 13_di_dong.png');
    await browser.close();
})().catch((e) => { console.error(e); process.exit(1); });
