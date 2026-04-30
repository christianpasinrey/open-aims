<script setup lang="ts">
import { computed } from 'vue';
import {
    Briefcase,
    Bug,
    Calendar,
    Code,
    Compass,
    Cpu,
    FileText,
    Flag,
    Flame,
    FlaskConical,
    Gauge,
    Globe,
    Hammer,
    Heart,
    Image,
    Layers,
    Leaf,
    Lightbulb,
    Lock,
    Map,
    Megaphone,
    Microscope,
    Music,
    Package,
    Palette,
    Rocket,
    Settings,
    Shield,
    ShoppingCart,
    Sparkles,
    Star,
    Sun,
    Target,
    Telescope,
    Truck,
    Users,
    Wrench,
    Zap,
} from 'lucide-vue-next';

const props = withDefaults(
    defineProps<{
        icon?: string | null;
        name?: string;
        color?: string | null;
        size?: number;
    }>(),
    { size: 20 },
);

// repo stores team icons as PascalCase identifiers (e.g. "Calendar",
// "Briefcase"). Map the common ones to lucide-vue-next icons. Anything
// we don't know about falls back to the first letter on a tinted bg.
const ICONS: Record<string, ReturnType<typeof Calendar>> = {
    Briefcase,
    Bug,
    Calendar,
    Code,
    Compass,
    Cpu,
    Document: FileText,
    FileText,
    Flag,
    Flame,
    FlaskConical,
    Gauge,
    Globe,
    Hammer,
    Heart,
    Image,
    Layers,
    Leaf,
    Lightbulb,
    Lock,
    Map,
    Megaphone,
    Microscope,
    Music,
    Package,
    Palette,
    Rocket,
    Settings,
    Shield,
    ShoppingCart,
    ShoppingBag: ShoppingCart,
    Sparkles,
    Star,
    Sun,
    Target,
    Telescope,
    Truck,
    Users,
    Wrench,
    Zap,
} as const as Record<string, ReturnType<typeof Calendar>>;

const lucideComponent = computed(() => {
    if (!props.icon) return null;

    return ICONS[props.icon] ?? null;
});

const initial = computed(() => (props.name ?? '?').charAt(0).toUpperCase());
const tint = computed(() => props.color ?? '#a1a1aa');
const iconSize = computed(() => Math.round(props.size * 0.7));
</script>

<template>
    <span
        class="relative inline-flex shrink-0 items-center justify-center overflow-hidden rounded-md"
        :style="{ width: `${size}px`, height: `${size}px` }"
        aria-hidden="true"
    >
        <span
            class="absolute inset-0"
            :style="{ backgroundColor: tint, opacity: 0.18 }"
        ></span>
        <component
            v-if="lucideComponent"
            :is="lucideComponent"
            class="relative"
            :style="{
                color: tint,
                width: `${iconSize}px`,
                height: `${iconSize}px`,
            }"
            :stroke-width="2.25"
        />
        <span
            v-else
            class="relative font-semibold uppercase tracking-tight"
            :style="{
                color: tint,
                fontSize: `${Math.round(size * 0.55)}px`,
            }"
            >{{ initial }}</span
        >
    </span>
</template>
