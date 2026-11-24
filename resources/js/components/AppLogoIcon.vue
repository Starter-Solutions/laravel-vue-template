<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 76 76"
        :class="className"
        v-bind="$attrs"
    >
        <path
            fill="currentColor"
            d="M 38,3.16666C 57.2379,3.16666 72.8333,18.7621 72.8333,38C 72.8333,57.2379 57.2379,72.8333 38,72.8333C 18.7621,72.8333 3.16667,57.2379 3.16667,38C 3.16667,18.7621 18.7621,3.16666 38,3.16666 Z M 52.252,18.9974L 36.4164,18.9974L 23.75,39.5833L 34.8333,39.5833L 25.3316,60.1667L 50.6667,34.8333L 38,34.8333L 52.252,18.9974 Z"
        />
    </svg>
</template>
