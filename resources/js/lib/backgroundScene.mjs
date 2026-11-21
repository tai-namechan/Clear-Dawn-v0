/**
 * Which wa-modern background a Clear Dawn page uses
 * (docs/clear-dawn-hero-design-guidelines.md, v3).
 */

/** @type {[RegExp, string][]} */
const SCENE_RULES = [
    [/^(Dashboard|Goals\/|LifeAreas\/)/, 'today'],
    [/^Meals\//, 'food'],
    [/^Records\//, 'condition'],
    [
        /^(Routines|Plans|Sessions|Programs|RoutineItems|History|Videos)\//,
        'practice',
    ],
    [/^settings\//, 'settings'],
];

/**
 * @param {string | null | undefined} component Inertia page component name
 * @returns {'today' | 'food' | 'condition' | 'practice' | 'settings'}
 */
export function backgroundScene(component) {
    const name = String(component ?? '');

    return /** @type {any} */ (
        SCENE_RULES.find(([pattern]) => pattern.test(name))?.[1] ?? 'settings'
    );
}
