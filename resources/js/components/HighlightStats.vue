<script setup lang="ts">
import { CheckCircle, Briefcase, Target } from 'lucide-vue-next';

defineProps<{
    highlights: {
        matching_skills?: number;
        relevant_years?: number;
        key_gaps?: number;
    };
}>();
</script>

<template>
    <div class="flex flex-wrap gap-3">
        <div
            v-if="highlights.matching_skills !== undefined"
            class="flex items-center gap-2 rounded-lg border border-border/50 bg-background/60 px-3 py-2"
        >
            <CheckCircle class="size-4 text-success" />
            <span class="text-sm font-medium text-foreground">
                {{ highlights.matching_skills }}
                {{
                    highlights.matching_skills === 1 ? 'skill' : 'skills'
                }}
                match
            </span>
        </div>

        <div
            v-if="highlights.relevant_years !== undefined"
            class="flex items-center gap-2 rounded-lg border border-border/50 bg-background/60 px-3 py-2"
        >
            <Briefcase class="size-4 text-primary" />
            <span class="text-sm font-medium text-foreground">
                {{ highlights.relevant_years }}
                {{ highlights.relevant_years === 1 ? 'year' : 'years' }}
                experience
            </span>
        </div>

        <div
            v-if="highlights.key_gaps !== undefined"
            class="flex items-center gap-2 rounded-lg border border-border/50 bg-background/60 px-3 py-2"
        >
            <Target class="size-4 text-warning" />
            <span class="text-sm font-medium text-foreground">
                {{ highlights.key_gaps }}
                {{ highlights.key_gaps === 1 ? 'area' : 'areas' }} to develop
            </span>
        </div>
    </div>
</template>
