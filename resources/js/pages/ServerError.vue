<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Button } from '@starter-solutions/vue-ui/base/button';
import { dashboard } from '@/routes';
</script>

<template>
    <main
        class="grid min-h-svh place-items-center bg-background p-6 text-center text-foreground"
    >
        <Head title="Server error" />

        <div class="flex max-w-sm flex-col items-center gap-6">
            <div class="space-y-2">
                <h1 class="text-3xl font-semibold">500 — Server error</h1>
                <p class="text-muted-foreground">
                    Something went wrong. Please try again in a moment.
                </p>
            </div>

            <Button as-child>
                <Link :href="dashboard()">Back to Dashboard</Link>
            </Button>
        </div>
    </main>
</template>
