import assert from 'node:assert/strict';
import { existsSync, readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';
import { fileURLToPath } from 'node:url';

const root = resolve(fileURLToPath(new URL('../../', import.meta.url)));
const source = (path) => readFileSync(resolve(root, path), 'utf8');

test('hero artwork and design tokens are available', () => {
    for (const file of [
        'public/images/decorations/cd-hero-dawn.svg',
        'public/images/decorations/cd-hero-halo.svg',
        'public/images/decorations/cd-hero-stars.svg',
        'docs/clear-dawn-hero-design-guidelines.md',
    ]) {
        assert.equal(existsSync(resolve(root, file)), true, file);
    }

    assert.match(source('resources/css/app.css'), /clear-dawn-hero\.css/);
    assert.match(source('resources/css/clear-dawn-hero.css'), /\.cd-clear-app/);
});

test('Clear Dawn layout scopes its style and navigation to its own shell', () => {
    const app = source('resources/js/layouts/app/AppSidebarLayout.vue');
    assert.match(app, /cd-clear-app/);
    assert.match(app, /CdMobileNav/);
    const resolver = source('resources/js/app.ts');
    assert.match(
        resolver,
        /name\.startsWith\('Yoyu\/'\) \|\| name\.startsWith\('Kioku\/'\)/,
    );
    assert.match(resolver, /return OsShellLayout/);
});

test('primary Clear Dawn pages use the hero design', () => {
    const directHero = [
        'Goals/Index',
        'Goals/Show',
        'History/Index',
        'LifeAreas/Index',
        'Meals/Foods',
        'Plans/Show',
        'Programs/Index',
        'Programs/Roadmap',
        'Programs/Show',
        'Records/Show',
        'Records/Strength',
        'RoutineItems/Index',
        'RoutineItems/Show',
        'Routines/Show',
        'Sessions/Show',
        'Videos/Index',
    ];

    for (const page of directHero) {
        assert.match(
            source('resources/js/pages/' + page + '.vue'),
            /<PageSectionCard hero>/,
            page,
        );
    }

    for (const page of [
        'Records/Index',
        'Records/Condition',
        'Meals/Index',
        'Routines/Index',
    ]) {
        assert.match(
            source('resources/js/pages/' + page + '.vue'),
            /<PageTabShell/,
            page,
        );
    }

    assert.match(
        source('resources/js/pages/Dashboard.vue'),
        /class="cd-hero-banner cd-home-hero/,
    );
});

test('each screen uses its wa-modern background, with ink text on the washi', () => {
    for (const scene of [
        'today',
        'food',
        'condition',
        'practice',
        'settings',
    ]) {
        for (const size of ['sp', 'pc']) {
            const file = `public/images/backgrounds/cd-wa-${scene}-${size}.webp`;
            assert.equal(existsSync(resolve(root, file)), true, file);
        }
    }

    const app = source('resources/js/layouts/app/AppSidebarLayout.vue');
    assert.match(app, /backgroundScene\(page\.component\)/);
    assert.match(app, /cd-wa-bg cd-wa-\$\{scene\.value\}/);

    const css = source('resources/css/clear-dawn-hero.css');
    assert.match(
        css,
        /\.cd-wa-food \{\s*background-image: url\('\/images\/backgrounds\/cd-wa-food-sp\.webp'\)/,
    );
    assert.match(
        css,
        /\.cd-hero-heading h1,\s*\.cd-clear-app \.cd-hero-banner h1 \{\s*color: var\(--cd-hero-ink\)/,
    );
    assert.doesNotMatch(css, /cd-hero-text-shadow/);
    assert.match(css, /--cd-hero-primary: #6f68ad/);
    assert.match(css, /--cd-hero-surface: rgba\(253, 250, 244, 0\.97\)/);
});

test('tab-shell pages put hero figures in a white summary card', () => {
    const shell = source('resources/js/components/PageTabShell.vue');
    assert.match(
        shell,
        /class="cd-panel cd-hero-summary">\s*<slot name="hero" \/>/,
    );
    assert.match(shell, /class="cd-hero-stage"/);
});

test('the running-session controls sit above the mobile navigation', () => {
    assert.match(
        source('resources/js/pages/Sessions/Show.vue'),
        /class="cd-session-actionbar/,
    );
    assert.match(
        source('resources/css/clear-dawn-hero.css'),
        /\.cd-session-actionbar \{[^}]*bottom: calc\(4\.6rem \+ env\(safe-area-inset-bottom\)\)/,
    );
});
