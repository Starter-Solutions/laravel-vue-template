<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Button } from '@starter-solutions/vue-ui/base/button';
import { dashboard } from '@/routes';
</script>

<template>
    <main
        class="grid min-h-svh place-items-center bg-background p-6 text-center text-foreground"
    >
        <Head title="Page not found" />

        <div class="flex max-w-sm flex-col items-center gap-6">
            <div class="space-y-2">
                <h1 class="text-3xl font-semibold">404 — Page not found</h1>
                <p class="text-muted-foreground">
                    The page you’re looking for doesn’t exist.
                </p>
            </div>

            <Button as-child>
                <Link :href="dashboard()">Back to Dashboard</Link>
            </Button>
        </div>
    </main>
</template>
