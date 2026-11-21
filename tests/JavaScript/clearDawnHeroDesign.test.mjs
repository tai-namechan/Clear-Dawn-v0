import assert from 'node:assert/strict';
import { existsSync, readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import test from 'node:test';

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
    assert.match(resolver, /name\.startsWith\('Yoyu\/'\) \|\| name\.startsWith\('Kioku\/'\)/);
    assert.match(resolver, /return OsShellLayout/);
});

test('primary Clear Dawn pages use the hero design', () => {
    const directHero = [
        'Goals/Index','Goals/Show','History/Index','LifeAreas/Index',
        'Meals/Foods','Plans/Show','Programs/Index','Programs/Roadmap',
        'Programs/Show','Records/Show','Records/Strength',
        'RoutineItems/Index','RoutineItems/Show','Routines/Show',
        'Sessions/Show','Videos/Index',
    ];
    for (const page of directHero) {
        assert.match(
            source('resources/js/pages/' + page + '.vue'),
            /<PageSectionCard hero>/,
            page,
        );
    }
    for (const page of ['Records/Index','Records/Condition','Meals/Index','Routines/Index']) {
        assert.match(source('resources/js/pages/' + page + '.vue'), /<PageTabShell/, page);
    }
    assert.match(source('resources/js/pages/Dashboard.vue'), /<CdHeroBanner/);
});

test('mobile navigation never overrides the running-session controls', () => {
    assert.match(source('resources/js/components/CdMobileNav.vue'), /startsWith\('\\/sessions\\/'\)/);
});
