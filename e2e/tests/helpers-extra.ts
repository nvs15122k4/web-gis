import { Page } from '@playwright/test';

/** Lỗi console do tile nền bên thứ ba / mạng ngoài, không thuộc ứng dụng. */
export const isExternalNoise = (e: string) => /arcgisonline|ERR_|Failed to load resource/i.test(e);

/** Gom lỗi console + pageerror từ lúc gọi. */
export function collectErrors(page: Page) {
    const errors: string[] = [];
    page.on('console', (m) => m.type() === 'error' && errors.push(m.text()));
    page.on('pageerror', (e) => errors.push(e.message));
    return () => errors.filter((e) => !isExternalNoise(e));
}

export async function apiLogin(page: Page, email: string, password: string) {
    const res = await page.request.post('/api/auth/login', { data: { email, password } });
    return { res, body: await res.json().catch(() => ({})) };
}
