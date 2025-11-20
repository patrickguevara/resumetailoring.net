<script setup lang="ts">
import { cn } from '@/lib/utils';
import type { HTMLAttributes } from 'vue';
import { computed, useAttrs } from 'vue';

defineOptions({
    inheritAttrs: false,
});

interface Props {
    className?: HTMLAttributes['class'];
}

const props = defineProps<Props>();
const attrs = useAttrs();

const altText = computed(() => {
    const alt = attrs.alt as string | undefined;

    return typeof alt === 'string' && alt.length > 0
        ? alt
        : 'Resume Tailor logo';
});

const otherAttrs = computed(() => {
    const clone = { ...(attrs as Record<string, unknown>) };

    delete clone.class;
    delete clone.alt;

    return clone;
});

const imageClass = computed(() =>
    cn(
        'object-contain',
        attrs.class as HTMLAttributes['class'],
        props.className,
    ),
);
</script>

<template>
    <img
        src="/logo.png"
        :alt="altText"
        :class="imageClass"
        v-bind="otherAttrs"
    />
</template>
