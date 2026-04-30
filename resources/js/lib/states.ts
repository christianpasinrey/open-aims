/**
 * repo-style helpers for workflow states.
 *
 * repo computes the "started" arc-fill from a state's rank within the
 * team's set of started states: 1 state → 50%, 2 → 25%/75%, 3 → 16%/50%/83%.
 * "In Review" reads visually as ~3/4 full because it's the last started
 * state of two. We expose a Map<id, progress> so callers can pass the
 * fraction straight to <StatusIcon :progress=…>.
 */
export type StateLite = {
    id: number;
    type: string;
    color?: string | null;
    position: number;
};

export function startedProgressByState(states: StateLite[]): Record<number, number> {
    const out: Record<number, number> = {};
    const started = states
        .filter((s) => s.type === 'started')
        .sort((a, b) => a.position - b.position);
    const total = started.length;
    started.forEach((s, i) => {
        out[s.id] = total === 0 ? 0.5 : (i * 2 + 1) / (total * 2);
    });
    return out;
}
