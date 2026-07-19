/**
 * Record V-BETA-01…09 review videos via Playwright (1280×720 viewport — no screen zoom).
 *
 * Usage: node scripts/record-beta-videos.mjs [filter]
 * Requires: CMS on BIRKLY_BASE_URL (default http://127.0.0.1:8080)
 */
import { chromium } from 'playwright';
import { execSync } from 'child_process';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(__dirname, '..');
const BASE = process.env.BIRKLY_BASE_URL || 'http://127.0.0.1:8080';
const OUT_DIR = path.join(ROOT, 'docs/reviews/artifacts');
const ADMIN_EMAIL = process.env.BETA_VIDEO_ADMIN_EMAIL || 'beta-review@birkly.local';
const ADMIN_PASSWORD = process.env.BETA_VIDEO_ADMIN_PASSWORD || 'BetaReview123!';
const VIEWPORT = { width: 1280, height: 720 };
const MIN_DURATION_SEC = 4;
const MIN_BYTES = 80_000;
const MIN_HOLD_MS = 6000;

const filter = process.argv[2] || '';

function prep(project) {
    execSync(`./scripts/prepare-reference-demo.sh ${project}`, { cwd: ROOT, stdio: 'inherit' });
}

function sleep(ms) {
    return new Promise((r) => setTimeout(r, ms));
}

async function hold(page, ms = MIN_HOLD_MS) {
    const step = 500;
    for (let t = 0; t < ms; t += step) {
        await sleep(step);
    }
}

function validateVideo(mp4Path) {
    if (!fs.existsSync(mp4Path)) {
        throw new Error(`missing output: ${mp4Path}`);
    }
    const size = fs.statSync(mp4Path).size;
    if (size < MIN_BYTES) {
        throw new Error(`${path.basename(mp4Path)} too small (${size} bytes)`);
    }
    const probe = execSync(
        `ffprobe -v error -show_entries format=duration -of csv=p=0 "${mp4Path}"`,
        { encoding: 'utf8' }
    ).trim();
    const duration = parseFloat(probe);
    if (!Number.isFinite(duration) || duration < MIN_DURATION_SEC) {
        throw new Error(`${path.basename(mp4Path)} too short (${duration}s)`);
    }
    execSync(`ffprobe -v error "${mp4Path}"`, { stdio: 'pipe' });
}

async function typeSlow(locator, text) {
    await locator.click();
    await locator.pressSequentially(text, { delay: 60 });
}

async function scrollPage(page, total = 600) {
    const step = 150;
    for (let y = 0; y < total; y += step) {
        await page.mouse.wheel(0, step);
        await sleep(350);
    }
}

async function waitRendered(page, minLen = 80) {
    await page.waitForFunction(
        (n) => {
            const main = document.querySelector('main') || document.body;
            const t = (main?.innerText || '').trim();
            return t.length > n && !t.includes('{for each') && !t.includes('{ title }');
        },
        minLen,
        { timeout: 25000 }
    );
    await sleep(1200);
}

async function createAdminStorageState() {
    const browser = await chromium.launch({ headless: true, slowMo: 80 });
    const context = await browser.newContext({ viewport: VIEWPORT });
    const page = await context.newPage();
    await page.goto(`${BASE}/admin/login.php`, { waitUntil: 'domcontentloaded' });
    await page.locator('input[name="email"]').fill(ADMIN_EMAIL);
    await page.locator('input[name="password"]').fill(ADMIN_PASSWORD);
    await page.locator('button[type="submit"]').first().click();
    await page.waitForURL(/admin\/index\.php/, { timeout: 15000 });
    await sleep(800);
    const state = await context.storageState();
    await browser.close();
    return state;
}

function convertWebmToMp4(webmPath, mp4Dest) {
    execSync(
        [
            'ffmpeg -y',
            `-i "${webmPath}"`,
            '-c:v libx264 -preset fast -crf 23',
            '-pix_fmt yuv420p -movflags +faststart',
            `"${mp4Dest}"`,
        ].join(' '),
        { stdio: 'inherit' }
    );
    if (fs.existsSync(webmPath)) fs.unlinkSync(webmPath);
}

async function record(id, filename, fn, { storageState } = {}) {
    if (filter && filter !== id) return;
    console.log(`\n[record] ${id} → ${filename}`);
    const browser = await chromium.launch({ headless: true, slowMo: 80 });
    const contextOpts = {
        viewport: VIEWPORT,
        recordVideo: { dir: OUT_DIR, size: VIEWPORT },
    };
    if (storageState) contextOpts.storageState = storageState;
    const context = await browser.newContext(contextOpts);
    const page = await context.newPage();
    try {
        await fn(page);
        await hold(page);
    } finally {
        const video = page.video();
        await page.close();
        await context.close();
        await browser.close();
        if (!video) throw new Error('no video object');
        const webmPath = await video.path();
        await sleep(500);
        const mp4Dest = path.join(OUT_DIR, filename);
        convertWebmToMp4(webmPath, mp4Dest);
        validateVideo(mp4Dest);
        console.log(`[record] OK ${mp4Dest} (${fs.statSync(mp4Dest).size} bytes)`);
    }
}

async function recordInstall() {
    const port = 18092;
    const installBase = `http://127.0.0.1:${port}`;
    let pid = '';
    try {
        execSync('./scripts/build-cms-release.sh', { cwd: ROOT, stdio: 'pipe' });
        const zip = '/tmp/birkly-release.zip';
        const extractDir = `/tmp/birkly-video-install-${Date.now()}`;
        execSync(`rm -rf "${extractDir}" && mkdir -p "${extractDir}" && unzip -q "${zip}" -d "${extractDir}"`);
        const inner = fs.readdirSync(extractDir).find((d) => fs.existsSync(path.join(extractDir, d, 'server-router.php')));
        const cmsRoot = inner ? path.join(extractDir, inner) : extractDir;
        pid = execSync(
            `php -S 127.0.0.1:${port} -t "${cmsRoot}" "${cmsRoot}/server-router.php" >/dev/null 2>&1 & echo $!`
        ).toString().trim();

        await record('01', 'v-beta-01-install.mp4', async (page) => {
            const startHere = fs.existsSync(path.join(cmsRoot, 'START_HERE.html'))
                ? `${installBase}/START_HERE.html`
                : `${installBase}/0_START-HERE.html`;
            await page.goto(startHere, { waitUntil: 'networkidle' });
            await sleep(3000);
            await page.mouse.wheel(0, 300);
            await sleep(1500);
            await page.goto(`${installBase}/admin/login.php`, { waitUntil: 'domcontentloaded' });
            await sleep(2000);
            if ((await page.locator('#first-user-form').count()) > 0) {
                await typeSlow(page.locator('#first-user-name, input[name="name"]').first(), 'Install Demo');
                await typeSlow(page.locator('#first-user-email, input[name="email"]').first(), 'install-demo@birkly.local');
                await typeSlow(page.locator('#first-user-password, input[name="password"]').first(), 'InstallDemo123!');
                const confirm = page.locator('#first-user-password-confirm, input[name="password_confirm"]');
                if (await confirm.count()) await typeSlow(confirm.first(), 'InstallDemo123!');
                await sleep(800);
                await page.locator('#first-user-form button[type="submit"], #first-user-form button').first().click();
                await sleep(4000);
            }
            const getStarted = page.locator('button:has-text("Get Started"), button:has-text("Skip Setup")');
            if (await getStarted.count()) {
                await getStarted.first().click();
                await sleep(3000);
            }
            const skip = page.locator('button:has-text("Skip"), [data-action="skip"]');
            if (await skip.count()) {
                await skip.first().click();
                await sleep(3000);
            }
            await sleep(3000);
            const body = await page.locator('body').innerText();
            const ok =
                body.includes('Dashboard') ||
                body.includes('Collections') ||
                body.includes('Welcome to Birkly') ||
                body.includes('Birkly is ready');
            if (!ok) {
                throw new Error('install video: post-install admin not visible');
            }
        });
    } finally {
        if (pid) {
            try { execSync(`kill ${pid} 2>/dev/null`); } catch { /* ignore */ }
        }
    }
}

async function main() {
    fs.mkdirSync(OUT_DIR, { recursive: true });
    execSync('php scripts/prepare-beta-video-admin.php', { cwd: ROOT, stdio: 'inherit', env: { ...process.env, BIRKLY_BASE_URL: BASE } });
    const adminState = await createAdminStorageState();

    if (!filter || filter === '01') await recordInstall();

    await record('02', 'v-beta-02-blog.mp4', async (page) => {
        prep('blog');
        await page.goto(`${BASE}/reference-projects/blog/website/index.html`, { waitUntil: 'domcontentloaded' });
        await waitRendered(page, 100);
        await page.mouse.wheel(0, 250);
        await sleep(1500);
        const postLink = page.locator('a[href*="post.html"]').first();
        await postLink.click();
        await waitRendered(page, 80);
        const body = await page.locator('body').innerText();
        if (body.includes('{ content }') || body.includes('{for each') || body.length < 120) {
            throw new Error('blog video: post content not rendered');
        }
        await sleep(2500);
    });

    await record('03', 'v-beta-03-coffee.mp4', async (page) => {
        prep('coffee');
        await page.goto(`${BASE}/reference-projects/coffee/website/product.html?slug=ethiopian-single-origin`, { waitUntil: 'domcontentloaded' });
        await waitRendered(page, 60);
        const body = await page.locator('body').innerText();
        if (!body.includes('Ethiopian') || body.includes('{for each variant')) {
            throw new Error('coffee video: variants not rendered');
        }
        await sleep(2000);
        await scrollPage(page, 200);
        const addBtn = page.locator('form[data-birkly-action="add"] button[type="submit"]').first();
        if (!(await addBtn.count())) throw new Error('coffee video: add to cart button missing');
        await addBtn.scrollIntoViewIfNeeded();
        await sleep(800);
        await addBtn.click();
        await sleep(4000);
        const after = await page.locator('body').innerText();
        if (!after.includes('1 item') && !after.includes('1 items')) {
            throw new Error('coffee video: cart count not updated');
        }
        await sleep(2000);
    });

    await record('04', 'v-beta-04-yoga.mp4', async (page) => {
        prep('yoga');
        await page.goto(`${BASE}/reference-projects/yoga/website/index.html`, { waitUntil: 'domcontentloaded' });
        await waitRendered(page, 60);
        await sleep(2000);
        await page.goto(`${BASE}/reference-projects/yoga/website/event.html?slug=sunrise-vinyasa`, { waitUntil: 'domcontentloaded' });
        await waitRendered(page, 60);
        const body = await page.locator('body').innerText();
        if (!body.includes('Sunrise Vinyasa') || !body.includes('Register')) {
            throw new Error('yoga video: event detail not rendered');
        }
        await sleep(3000);
    });

    await record('05', 'v-beta-05-marketplace.mp4', async (page) => {
        await page.goto(`${BASE}/admin/index.php?page=plugins&tab=marketplace`, { waitUntil: 'domcontentloaded' });
        await sleep(2500);
        const body = await page.locator('body').innerText();
        if (body.includes('Sign In') || !body.includes('Marketplace')) {
            throw new Error('marketplace video: not on marketplace tab');
        }
        await sleep(2500);
        await scrollPage(page, 500);
        const plugin = page.locator('.marketplace-card, .plugin-card, [data-plugin-id]').first();
        if (await plugin.count()) {
            await plugin.scrollIntoViewIfNeeded();
            await sleep(2000);
        }
    }, { storageState: adminState });

    await record('06', 'v-beta-06-admin-publish.mp4', async (page) => {
        prep('blog');
        await page.goto(`${BASE}/admin/index.php?page=entries&collection=blog`, { waitUntil: 'domcontentloaded' });
        await sleep(2500);
        const body = await page.locator('body').innerText();
        if (body.includes('Sign In')) throw new Error('admin publish video: not logged in');
        const entry = page.locator('a[href*="entry"], tr a, .entry-row a').first();
        if (await entry.count()) {
            await entry.click();
            await sleep(3500);
        }
        await sleep(2000);
    }, { storageState: adminState });

    await record('07', 'v-beta-07-social.mp4', async (page) => {
        prep('social');
        await page.goto(`${BASE}/reference-projects/social/website/index.html`, { waitUntil: 'domcontentloaded' });
        await waitRendered(page, 80);
        const body = await page.locator('body').innerText();
        if (!body.includes('HaruSocial') || body.includes('{for each entry')) {
            throw new Error('social video: feed not rendered');
        }
        await sleep(2000);
        await scrollPage(page, 700);
        await sleep(2000);
    });

    await record('08', 'v-beta-08-kekaba.mp4', async (page) => {
        const kekaba = path.resolve(ROOT, '..', 'kekaba');
        if (!fs.existsSync(kekaba)) throw new Error('kekaba repo not found');
        execSync(`./scripts/migrate-kekaba-live-path.sh "${kekaba}"`, { cwd: ROOT, stdio: 'pipe' });
        const port = 18093;
        let pid = '';
        try {
            pid = execSync(
                `cd "${kekaba}" && python3 -m http.server ${port} >/dev/null 2>&1 & echo $!`
            ).toString().trim();
            await sleep(1000);
            await page.goto(`http://127.0.0.1:${port}/index.html`, { waitUntil: 'domcontentloaded' });
            await sleep(5000);
            const body = await page.locator('body').innerText();
            if (body.length < 50) throw new Error('kekaba video: page empty');
            await page.mouse.wheel(0, 600);
            await sleep(3000);
        } finally {
            if (pid) try { execSync(`kill ${pid} 2>/dev/null`); } catch { /* ignore */ }
        }
    });

    await record('09', 'v-beta-09-ops.mp4', async (page) => {
        try {
            execSync(`BIRKLY_SMOKE_SKIP_MARKETPLACE=1 ./scripts/scheduled-production-smoke.sh "${BASE}"`, {
                cwd: ROOT,
                stdio: 'pipe',
            });
        } catch {
            // Ops tab can still show last run from logs even when marketplace is unreachable.
        }
        await page.goto(`${BASE}/admin/index.php?page=settings`, { waitUntil: 'domcontentloaded' });
        await sleep(1500);
        const opsTab = page.locator('button[data-tab="operations"]').first();
        if (!(await opsTab.count())) throw new Error('ops video: operations tab missing');
        await opsTab.click();
        await sleep(3500);
        const pane = page.locator('#operations');
        if (!(await pane.count())) throw new Error('ops video: operations pane missing');
    }, { storageState: adminState });

    console.log('\n[record] All videos recorded and validated.');
}

main().catch((err) => {
    console.error(err);
    process.exit(1);
});
