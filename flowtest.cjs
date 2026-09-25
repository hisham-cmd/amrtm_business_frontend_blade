const { chromium } = require('playwright');

(async () => {
    const browser = await chromium.launch();
    const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
    const errors = [];
    page.on('console', (m) => { if (m.type() === 'error') errors.push(m.text()); });
    page.on('pageerror', (e) => errors.push(String(e)));

    // 1) التسجيل عبر صفحة /register (نموذج إنشاء الحساب)
    await page.goto('http://127.0.0.1:8001/register', { waitUntil: 'networkidle', timeout: 25000 });
    await page.waitForTimeout(800);
    const email = 'flow_' + Date.now() + '@t.local';
    // نستدعي الـ API مباشرة لتسجيل حساب ثم نخزن في الجلسة عبر /login submit
    const reg = await page.evaluate(async (em) => {
        const r = await fetch('/api/v1/auth/register', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ name: 'مستخدم الجلسة', email: em, phone: '0551112222', password: 'Passw0rd!X', password_confirmation: 'Passw0rd!X', account_type: 'individual' }),
        });
        return await r.json();
    }, email);
    console.log('REGISTER via API: ' + (reg.isSuccess ? 'OK' : JSON.stringify(reg.error)));

    // 2) دخول عبر /login submit (يطمس التوكن في الجلسة)
    await page.goto('http://127.0.0.1:8001/login', { waitUntil: 'networkidle', timeout: 25000 });
    await page.waitForTimeout(500);
    await page.evaluate((em) => {
        // حقن الجلسة محلياً عبر POST المتوقع — الأبسط: نكتب في الـ GET بنفس الطريقة التي يعمل بها form
        const form = document.querySelector('form');
        if (form) {
            const inp = document.createElement('input');
            inp.type = 'email'; inp.name = 'email'; inp.value = em;
            form.appendChild(inp);
            const p = document.createElement('input');
            p.type = 'password'; p.name = 'password'; p.value = 'Passw0rd!X';
            form.appendChild(p);
        }
    }, email);

    // POST مباشر إلى /login (submit)
    const loginResp = await page.evaluate(async (em) => {
        const fd = new FormData();
        fd.append('email', em);
        fd.append('password', 'Passw0rd!X');
        const r = await fetch('/login', { method: 'POST', body: fd, headers: { 'Accept': 'text/html' } });
        return { status: r.status, url: r.url, redirected: r.redirected };
    }, email);
    console.log('LOGIN POST: ' + JSON.stringify(loginResp));

    // 3) الوصول للوحة بعد الدخول
    await page.goto('http://127.0.0.1:8001/dashboard', { waitUntil: 'networkidle', timeout: 25000 });
    await page.waitForTimeout(1000);
    const dash = await page.evaluate(() => ({
        url: location.pathname,
        text: document.body.innerText.slice(0, 150).replace(/\n/g, ' | '),
        hasLoginForm: !!document.querySelector('#login-email, input[name="email"]'),
    }));
    console.log('DASHBOARD: ' + JSON.stringify(dash));

    console.log('JS ERRORS: ' + errors.length);
    await browser.close();
})();