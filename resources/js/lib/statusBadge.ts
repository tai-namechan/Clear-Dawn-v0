/**
 * Shared solid status-pill classes (filled + white text).
 * Use for workflow / publication status chips across Clear Dawn screens.
 */
export const statusBadgeSolidBase =
    'inline-flex shrink-0 items-center gap-1 rounded-full px-2 py-0.5 font-sans text-xs font-medium text-white';

/** Plan run status on routines today cards */
export const planRunStatusBadgeClasses = {
    completed: 'bg-cd-moss text-white',
    in_progress: 'bg-cd-sunrise text-white',
    not_started: 'bg-cd-ink-muted text-white',
} as const;

/** Goal workflow status */
export const goalStatusBadgeClasses: Record<string, string> = {
    draft: 'bg-cd-ink-muted text-white',
    active: 'bg-cd-sunrise text-white',
    achieved: 'bg-cd-moss text-white',
    abandoned: 'bg-cd-ink-muted text-white line-through',
};

/** Program list status */
export const programStatusBadgeClasses: Record<string, string> = {
    draft: 'bg-cd-ink-muted text-white',
    active: 'bg-cd-sunrise text-white',
    completed: 'bg-cd-moss text-white',
    archived: 'bg-cd-ink-muted text-white',
};

/** Life area publication status */
export const lifeAreaPublicationBadgeClasses = {
    active: 'bg-cd-moss text-white',
    inactive: 'bg-cd-ink-muted text-white',
} as const;
