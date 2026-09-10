/**
 * Types and helpers shared by the graph viewer, the per-owner Graphs section
 * and the workspace map. Shapes mirror the Graphs module JSON endpoints.
 */

export type GraphKind = 'file' | 'class' | 'function' | 'resource';

export type GraphStage = 'planned' | 'implemented' | 'observed';

export type GraphNodeData = {
    id: number;
    key: string;
    kind: GraphKind;
    label: string;
    file: string | null;
    language: string | null;
    namespace: string | null;
    class: string | null;
    class_type: string | null;
    function: string | null;
    signature: string | null;
    visibility: string | null;
    resource_type: string | null;
    resource_name: string | null;
    context: string | null;
    role: string | null;
    change: string | null;
    description: string | null;
    purpose: string | null;
    summary: string | null;
    parent_id: number | null;
    graphs: number;
};

export type GraphLinkData = {
    source: number;
    target: number;
    relation: string;
    label: string | null;
    weight: number;
};

export type GraphData = {
    nodes: GraphNodeData[];
    links: GraphLinkData[];
};

export type GraphSummary = {
    id: number;
    title: string;
    summary: string;
    stage: GraphStage;
    version: number;
    repo: string;
    ref: string;
    updated_at: string | null;
};

/** Where a graph is attached: its issue, milestone or project. */
export type GraphOwnerRef = {
    type: 'issue' | 'project' | 'milestone';
    identifier: string;
    name: string;
    url: string;
};

/**
 * A graph listed in a Graphs section. `own` is false for graphs of the
 * milestones and issues of the project or milestone being viewed.
 */
export type OwnerGraphSummary = GraphSummary & {
    own: boolean;
    owner: GraphOwnerRef | null;
};

/** One graph with its nodes, from `GET /graphs/{id}`. */
export type OwnerGraph = GraphSummary & GraphData;

export type OwnerGraphsResponse = {
    owner: { type: string; id: number; name: string; url: string };
    graphs: OwnerGraphSummary[];
};

export type MapResponse = GraphData & {
    stats: { graphs: number; nodes: number; links: number };
};

export type ImpactResponse = {
    graph: { id: number; title: string; stage: GraphStage };
    depth: number;
    seeds: Array<{
        id: number;
        key: string;
        label: string;
        kind: GraphKind;
        change: string;
        visibility: string | null;
    }>;
    affected: Array<{
        id: number;
        key: string;
        label: string;
        kind: GraphKind;
        file: string | null;
        context: string | null;
        role: string | null;
        distance: number;
        via: {
            relation: string;
            from: string | null;
            from_label: string | null;
        };
    }>;
    tests_to_run: Array<{ key: string; label: string; file: string | null }>;
    collisions: Array<{
        graph_id: number;
        graph: string;
        stage: GraphStage;
        owner: {
            type: string;
            identifier: string;
            name: string;
            url: string;
        } | null;
        shared: Array<{ label: string; their_change: string }>;
    }>;
    signature_changes: Array<{
        key: string;
        label: string;
        visibility: string | null;
        from: string;
        to: string;
    }>;
    summary: {
        seeds: number;
        public_seeds: number;
        affected: number;
        by_context: Record<string, number>;
        by_role: Record<string, number>;
    };
};

export const CONTEXT_COLORS: Record<string, string> = {
    backend: '#6366f1',
    frontend: '#ec4899',
    tests: '#22c55e',
    database: '#f59e0b',
    infra: '#06b6d4',
    config: '#a855f7',
    docs: '#64748b',
    other: '#94a3b8',
};

export const RESOURCE_COLOR = '#f97316';

/** Relative node size: files are hubs, functions are leaves. */
export const KIND_SIZE: Record<GraphKind, number> = {
    file: 8,
    class: 5,
    function: 2.5,
    resource: 4,
};

export const STAGE_CLASSES: Record<GraphStage, string> = {
    planned:
        'border-amber-500/40 bg-amber-500/10 text-amber-600 dark:text-amber-400',
    implemented:
        'border-emerald-500/40 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
    observed: 'border-border bg-muted text-muted-foreground',
};

export function nodeColor(
    node: Pick<GraphNodeData, 'kind' | 'context'>,
): string {
    if (node.kind === 'resource') {
        return RESOURCE_COLOR;
    }

    return CONTEXT_COLORS[node.context ?? 'other'] ?? CONTEXT_COLORS.other;
}

export async function getJson<T>(
    url: string,
    signal?: AbortSignal,
): Promise<T> {
    const response = await fetch(url, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
        signal,
    });

    if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
    }

    return (await response.json()) as T;
}
