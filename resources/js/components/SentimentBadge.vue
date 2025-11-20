<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { AlertTriangle, CheckCircle, Info, ThumbsUp } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    sentiment: string;
}>();

const config = computed(() => {
    const configs = {
        strong_match: {
            label: 'Strong Match',
            icon: CheckCircle,
            className: 'border-success/30 bg-success/10 text-success',
        },
        good_match: {
            label: 'Good Match',
            icon: ThumbsUp,
            className: 'border-accent/30 bg-accent/10 text-accent-foreground',
        },
        partial_match: {
            label: 'Partial Match',
            icon: Info,
            className: 'border-warning/30 bg-warning/10 text-warning',
        },
        weak_match: {
            label: 'Needs Improvement',
            icon: AlertTriangle,
            className: 'border-muted/60 bg-muted/40 text-muted-foreground',
        },
    };

    return (
        configs[props.sentiment as keyof typeof configs] ?? configs.good_match
    );
});
</script>

<template>
    <Badge :class="config.className">
        <component :is="config.icon" class="mr-1 size-3" />
        {{ config.label }}
    </Badge>
</template>
