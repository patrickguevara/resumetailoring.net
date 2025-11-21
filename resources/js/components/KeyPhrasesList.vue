<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { computed } from 'vue';

const props = defineProps<{
    phrases: string[];
}>();

interface ColoredPhrase {
    text: string;
    sentiment: 'positive' | 'negative' | 'neutral';
}

const coloredPhrases = computed<ColoredPhrase[]>(() => {
    return props.phrases.map((phrase) => {
        const lowerPhrase = phrase.toLowerCase();

        // Negative indicators (gaps, weaknesses)
        const negativeKeywords = [
            'limited',
            'no ',
            'lack of',
            'missing',
            'gap',
            'weak',
            'insufficient',
            'not ',
            'does not',
            'absent',
            'without',
            'need',
            'should',
            'must improve',
            'consider',
        ];

        // Positive indicators (strengths)
        const positiveKeywords = [
            'strong',
            'proven',
            'accomplished',
            'extensive',
            'excellent',
            'demonstrated',
            'successful',
            'proficient',
            'skilled',
            'experienced',
            'led',
            'managed',
            'delivered',
            'achieved',
            'expert',
            'mastery',
        ];

        const hasNegative = negativeKeywords.some((keyword) =>
            lowerPhrase.includes(keyword),
        );
        const hasPositive = positiveKeywords.some((keyword) =>
            lowerPhrase.includes(keyword),
        );

        let sentiment: ColoredPhrase['sentiment'] = 'neutral';
        if (hasNegative && !hasPositive) {
            sentiment = 'negative';
        } else if (hasPositive && !hasNegative) {
            sentiment = 'positive';
        }

        return { text: phrase, sentiment };
    });
});

const getBadgeClass = (sentiment: ColoredPhrase['sentiment']) => {
    switch (sentiment) {
        case 'positive':
            return 'border-success/40 bg-success/15 text-success hover:bg-success/20';
        case 'negative':
            return 'border-destructive/40 bg-destructive/15 text-destructive hover:bg-destructive/20';
        default:
            return 'border-warning/40 bg-warning/15 text-warning hover:bg-warning/20';
    }
};
</script>

<template>
    <div class="flex flex-wrap gap-2">
        <Badge
            v-for="(phrase, index) in coloredPhrases"
            :key="index"
            :class="getBadgeClass(phrase.sentiment)"
            class="text-xs"
        >
            {{ phrase.text }}
        </Badge>
    </div>
</template>
