import { router, usePage } from '@inertiajs/vue3';
import { computed, type ComputedRef } from 'vue';

export type FavouriteKind =
    | 'view'
    | 'issue'
    | 'project'
    | 'cycle'
    | 'inbox'
    | 'team_view'
    | 'page';

export type Favourite = {
    id: number;
    kind: FavouriteKind;
    label: string;
    icon: string | null;
    color: string | null;
    href: string;
    target_id: number | null;
    target_type: string | null;
};

export type FavouriteToggleInput = {
    kind: FavouriteKind;
    href: string;
    label: string;
    icon?: string | null;
    color?: string | null;
    target_type?: string | null;
    target_id?: number | null;
};

type PageProps = {
    workspace?: { favourites?: Favourite[] | null } | null;
};

export function useFavourites(): {
    favourites: ComputedRef<Favourite[]>;
    isFavourited: (
        kind: FavouriteKind,
        hrefOrTargetId: string | number | null,
    ) => boolean;
    toggle: (item: FavouriteToggleInput) => void;
} {
    const page = usePage<PageProps>();

    const favourites = computed<Favourite[]>(() => {
        const list = page.props.workspace?.favourites;

        return Array.isArray(list) ? (list as Favourite[]) : [];
    });

    function isFavourited(
        kind: FavouriteKind,
        hrefOrTargetId: string | number | null,
    ): boolean {
        if (hrefOrTargetId === null || hrefOrTargetId === undefined) {
            return false;
        }

        return favourites.value.some((f) => {
            if (f.kind !== kind) {
                return false;
            }

            if (typeof hrefOrTargetId === 'number') {
                return f.target_id === hrefOrTargetId;
            }

            return f.href === hrefOrTargetId;
        });
    }

    function toggle(item: FavouriteToggleInput): void {
        const payload: Record<string, string | number | null> = {
            kind: item.kind,
            href: item.href,
            label: item.label,
        };

        if (item.icon !== undefined) {
            payload.icon = item.icon ?? null;
        }

        if (item.color !== undefined) {
            payload.color = item.color ?? null;
        }

        if (item.target_type !== undefined) {
            payload.target_type = item.target_type ?? null;
        }

        if (item.target_id !== undefined) {
            payload.target_id = item.target_id ?? null;
        }

        router.post('/favourites/toggle', payload, {
            preserveState: true,
            preserveScroll: true,
        });
    }

    return { favourites, isFavourited, toggle };
}
