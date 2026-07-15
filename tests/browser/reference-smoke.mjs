/**
 * Browser smoke for reference demo pages (Playwright).
 * Run: node tests/browser/reference-smoke.mjs
 * Requires: npm install && npx playwright install chromium
 */
import { chromium } from 'playwright';

const BASE = process.env.BIRKLY_BASE_URL || 'http://127.0.0.1:8080';

const filter = process.argv[2] || '';
const allCases = [
    {
        id: 'blog',
        name: 'blog post detail',
        url: `${BASE}/reference-projects/blog/website/post.html?slug=welcome-to-birkly`,
        must: ['Welcome to Birkly', 'cms'],
        mustNot: ['{ content }', '{item}', '{for each'],
    },
    {
        id: 'coffee',
        name: 'coffee product',
        url: `${BASE}/reference-projects/coffee/website/product.html?slug=ethiopian-single-origin`,
        must: ['Ethiopian', '12.99'],
        mustNot: ['{ title }', '{for each variant'],
    },
    {
        id: 'yoga',
        name: 'yoga event',
        url: `${BASE}/reference-projects/yoga/website/event.html?slug=sunrise-vinyasa`,
        must: ['Sunrise Vinyasa', 'Register for this class'],
        mustNot: ['{ description }', '{for each'],
    },
    {
        id: 'social',
        name: 'social feed',
        url: `${BASE}/reference-projects/social/website/index.html`,
        must: ['HaruSocial', 'Demo Admin'],
        mustNot: ['{for each entry', 'Stillwater Yoga', 'Maya Rivers'],
    },
    {
        id: 'influencer',
        name: 'influencer hub',
        url: `${BASE}/reference-projects/influencer/website/index.html`,
        must: ['Maya Rivers', 'Latest posts', 'Shop'],
        mustNot: ['{for each entry', '{ title }'],
    },
];

const cases = filter
    ? allCases.filter((c) => c.id === filter)
    : allCases;

async function waitForRender(page) {
    await page.waitForFunction(() => {
        const main = document.querySelector('main');
        if (!main) return false;
        const text = main.innerText || '';
        return text.length > 40 && !text.includes('{for each') && !text.includes('{ title }');
    }, { timeout: 15000 });
}

let failures = 0;
const browser = await chromium.launch({ headless: true });

for (const c of cases) {
    const context = await browser.newContext();
    await context.addInitScript(() => {
        try { sessionStorage.clear(); } catch (_) { /* ignore */ }
    });
    const page = await context.newPage();
    let caseFailed = false;
    try {
        await page.goto(c.url, { waitUntil: 'domcontentloaded' });
        await waitForRender(page);
        const body = await page.locator('body').innerText();
        for (const needle of c.must) {
            if (!body.includes(needle)) {
                console.error(`FAIL [${c.name}] missing: ${needle}`);
                caseFailed = true;
            }
        }
        for (const bad of c.mustNot) {
            if (body.includes(bad)) {
                console.error(`FAIL [${c.name}] found raw template: ${bad}`);
                caseFailed = true;
            }
        }
        if (!caseFailed) {
            console.log(`PASS [${c.name}]`);
        } else {
            failures++;
        }
    } catch (err) {
        console.error(`FAIL [${c.name}] ${err.message}`);
        failures++;
    } finally {
        await page.close();
        await context.close();
    }
}

await browser.close();
process.exit(failures > 0 ? 1 : 0);
