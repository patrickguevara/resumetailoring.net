<script setup lang="ts">
import { cn } from '@/lib/utils';
import MarkdownIt from 'markdown-it';
import { computed, ref, watch } from 'vue';

const markdown = new MarkdownIt({
    html: false,
    linkify: true,
    breaks: true,
});

const props = withDefaults(
    defineProps<{
        content?: string | null;
        emptyLabel?: string;
        contentClass?: string;
    }>(),
    {
        content: '',
        emptyLabel: 'No markdown content available.',
        contentClass: '',
    },
);

const mode = ref<'preview' | 'markdown'>('preview');

watch(
    () => props.content,
    () => {
        mode.value = 'preview';
    },
);

const isEmpty = computed(() => !props.content?.trim());

const rendered = computed(() =>
    props.content ? markdown.render(props.content) : '',
);
</script>

<template>
    <div class="space-y-3">
        <div class="flex items-center justify-end">
            <div
                class="inline-flex items-center gap-1 rounded-md border border-border/60 bg-muted/70 p-1 text-xs font-medium text-muted-foreground dark:border-border/40"
            >
                <button
                    type="button"
                    class="rounded-sm px-3 py-1 transition hover:text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary data-[active=true]:bg-background data-[active=true]:text-foreground"
                    :aria-pressed="mode === 'preview'"
                    :data-active="mode === 'preview'"
                    @click="mode = 'preview'"
                >
                    Preview
                </button>
                <button
                    type="button"
                    class="rounded-sm px-3 py-1 transition hover:text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary data-[active=true]:bg-background data-[active=true]:text-foreground"
                    :aria-pressed="mode === 'markdown'"
                    :data-active="mode === 'markdown'"
                    @click="mode = 'markdown'"
                >
                    Markdown
                </button>
            </div>
        </div>

        <div v-if="mode === 'preview'">
            <div
                v-if="!isEmpty"
                v-html="rendered"
                class="overflow-auto rounded-md border border-border/60 bg-background/60 p-4 text-sm leading-relaxed text-foreground/90 shadow-inner dark:border-border/40 [&_a]:text-primary [&_a]:underline-offset-4 [&_a:hover]:underline [&_blockquote]:border-l-2 [&_blockquote]:border-border/60 [&_blockquote]:ps-3 [&_blockquote]:italic [&_code]:rounded [&_code]:bg-muted/80 [&_code]:px-1 [&_code]:py-px [&_h1]:text-xl [&_h1]:font-semibold [&_h2]:text-lg [&_h2]:font-semibold [&_h3]:text-base [&_h3]:font-semibold [&_li]:ps-1 [&_ol]:list-decimal [&_ol]:ps-5 [&_p]:leading-relaxed [&_p]:text-foreground/90 [&_pre]:overflow-auto [&_pre]:rounded [&_pre]:bg-muted/80 [&_pre]:p-3 [&_strong]:font-semibold [&_ul]:list-disc [&_ul]:ps-5"
                :class="cn(contentClass)"
            />
            <p
                v-else
                class="rounded-md border border-dashed border-border/60 bg-muted/30 p-4 text-sm text-muted-foreground dark:border-border/40"
            >
                {{ emptyLabel }}
            </p>
        </div>
        <pre
            v-else
            class="overflow-auto rounded-md border border-border/60 bg-muted/40 p-4 text-sm leading-relaxed break-words whitespace-pre-wrap text-foreground/90 dark:border-border/40"
            :class="cn(contentClass)"
            >{{ content }}
</pre
        >
    </div>
</template>
