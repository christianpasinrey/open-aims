<script setup lang="ts">
import { useAppearance, type Appearance } from '@/composables/useAppearance';

const { appearance, updateAppearance } = useAppearance();

const options: { value: Appearance; label: string }[] = [
    { value: 'light', label: 'L' },
    { value: 'dark', label: 'D' },
    { value: 'system', label: 'A' },
];

const handleSelect = (value: Appearance) => {
    updateAppearance(value);
};
</script>

<template>
    <div
        class="inline-flex items-center gap-2 text-[10px] tracking-[0.2em] uppercase"
        style="font-family: 'IBM Plex Mono', ui-monospace, monospace"
        role="radiogroup"
        aria-label="Theme preference"
    >
        <span
            class="text-[#1A1A1A]/40 dark:text-[#F4EFE6]/40"
            aria-hidden="true"
            >Theme</span
        >
        <div class="flex items-center">
            <button
                v-for="(opt, i) in options"
                :key="opt.value"
                type="button"
                role="radio"
                :aria-checked="appearance === opt.value"
                :aria-label="
                    opt.value === 'light'
                        ? 'Light mode'
                        : opt.value === 'dark'
                          ? 'Dark mode'
                          : 'Auto (system)'
                "
                @click="handleSelect(opt.value)"
                :class="[
                    'relative px-2 py-1 transition-colors duration-150',
                    'focus:outline-none focus-visible:ring-1 focus-visible:ring-[#B8431B] dark:focus-visible:ring-[#D4582A]',
                    appearance === opt.value
                        ? 'text-[#1A1A1A] dark:text-[#F4EFE6]'
                        : 'text-[#1A1A1A]/40 hover:text-[#1A1A1A]/70 dark:text-[#F4EFE6]/40 dark:hover:text-[#F4EFE6]/70',
                    i !== options.length - 1
                        ? 'border-r border-[#1A1A1A]/15 dark:border-[#F4EFE6]/15'
                        : '',
                ]"
            >
                {{ opt.label }}
                <span
                    v-if="appearance === opt.value"
                    aria-hidden="true"
                    class="absolute right-2 -bottom-px left-2 h-px bg-[#B8431B] dark:bg-[#D4582A]"
                ></span>
            </button>
        </div>
    </div>
</template>
