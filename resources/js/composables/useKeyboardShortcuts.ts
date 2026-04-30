import { router, usePage } from '@inertiajs/vue3';
import type { Ref } from 'vue';
import { onBeforeUnmount, onMounted, ref } from 'vue';

type WorkspaceTeam = { id: number; name: string; key: string };
type WorkspaceProp = { teams?: WorkspaceTeam[] } | null;

/**
 * Returns true if the user is currently typing in a form field — we should
 * not hijack single-letter shortcuts in that case.
 */
function isTypingTarget(target: EventTarget | null): boolean {
    if (!(target instanceof HTMLElement)) {
        return false;
    }

    if (target.closest('[data-no-shortcut]')) {
        return true;
    }

    const tag = target.tagName;

    if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') {
        return true;
    }

    if (target.isContentEditable) {
        return true;
    }

    return false;
}

/**
 * Pulls the team key from the current URL query (?team=KEY) when present so
 * `g i` etc. can preserve team context.
 */
function currentTeamKey(): string | null {
    if (typeof window === 'undefined') {
        return null;
    }

    const params = new URLSearchParams(window.location.search);
    const t = params.get('team');

    return t && t.trim().length > 0 ? t : null;
}

function defaultTeamKey(): string | null {
    const page = usePage();
    const ws = (page.props as { workspace?: WorkspaceProp }).workspace ?? null;
    const teams = ws?.teams ?? [];

    return teams.length > 0 ? (teams[0]?.key ?? null) : null;
}

/**
 * Global keyboard shortcuts wired into AppLayout.
 *
 * - g i / g p / g c / g h — go to issues / projects / cycles / home
 * - c                       — open new-issue dialog (when not typing)
 * - ?                       — open keyboard shortcuts cheat sheet
 *
 * Cmd/Ctrl+K is owned by AppSidebar (search palette) — we don't duplicate it
 * here. We use `keyup` for letter combos so the user has time to release `g`.
 */
export function useKeyboardShortcuts(): {
    cheatSheetOpen: Ref<boolean>;
    composerOpen: Ref<boolean>;
    composerTeamKey: Ref<string | null>;
    closeComposer: () => void;
} {
    const cheatSheetOpen = ref<boolean>(false);
    const composerOpen = ref<boolean>(false);
    const composerTeamKey = ref<string | null>(null);

    let goPending = false;
    let goTimer: ReturnType<typeof setTimeout> | null = null;

    function clearGo(): void {
        goPending = false;

        if (goTimer) {
            clearTimeout(goTimer);
            goTimer = null;
        }
    }

    function teamFor(path: string): string {
        const key = currentTeamKey() ?? defaultTeamKey();

        return key ? `${path}?team=${encodeURIComponent(key)}` : path;
    }

    function navigate(href: string): void {
        router.get(href);
    }

    function openComposer(): void {
        const key = currentTeamKey() ?? defaultTeamKey();

        if (!key) {
            return;
        }

        composerTeamKey.value = key;
        composerOpen.value = true;
    }

    function closeComposer(): void {
        composerOpen.value = false;
        composerTeamKey.value = null;
    }

    function onKeyup(e: KeyboardEvent): void {
        if (e.metaKey || e.ctrlKey || e.altKey) {
            return;
        }

        if (isTypingTarget(e.target)) {
            return;
        }

        const key = e.key.toLowerCase();

        // `?` opens the cheat sheet (Shift+/ on most layouts).
        if (e.key === '?') {
            e.preventDefault();
            cheatSheetOpen.value = true;

            return;
        }

        if (goPending) {
            clearGo();

            switch (key) {
                case 'i':
                    e.preventDefault();
                    navigate(teamFor('/issues'));

                    return;
                case 'p':
                    e.preventDefault();
                    navigate('/projects');

                    return;
                case 'c':
                    e.preventDefault();
                    navigate(teamFor('/cycles'));

                    return;
                case 'h':
                    e.preventDefault();
                    navigate('/');

                    return;
                default:
                    return;
            }
        }

        if (key === 'g') {
            goPending = true;
            goTimer = setTimeout(clearGo, 1500);

            return;
        }

        if (key === 'c') {
            e.preventDefault();
            openComposer();
        }
    }

    onMounted(() => {
        window.addEventListener('keyup', onKeyup);
    });

    onBeforeUnmount(() => {
        window.removeEventListener('keyup', onKeyup);
        clearGo();
    });

    return {
        cheatSheetOpen,
        composerOpen,
        composerTeamKey,
        closeComposer,
    };
}
