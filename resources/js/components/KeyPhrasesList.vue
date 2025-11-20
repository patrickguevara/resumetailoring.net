<script setup lang="ts">
import { Badge } from '@/components/ui/badge';

defineProps<{
    phrases: string[];
}>();
</script>

<template>
    <div class="flex flex-wrap gap-2">
        <Badge
            v-for="(phrase, index) in phrases"
            :key="index"
            variant="secondary"
            class="text-xs"
        >
            {{ phrase }}
        </Badge>
    </div>
</template>
