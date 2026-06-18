<script setup lang="ts">
import { Moon, Sun, ArrowLeft } from '@lucide/vue';
import { Toaster } from '@/components/ui/sonner';
import AppLogo from '@/components/AppLogo.vue';
import { useAppearance } from '@/composables/useAppearance';

const { resolvedAppearance, updateAppearance } = useAppearance();

function toggleTheme(): void {
    updateAppearance(resolvedAppearance.value === 'dark' ? 'light' : 'dark');
}
</script>

<template>
    <div class="min-h-screen bg-background text-foreground">
        <!-- Minimal sticky nav -->
        <header class="sticky top-0 z-50 h-12 border-b border-border bg-background/80 backdrop-blur-sm">
            <div class="mx-auto flex h-full max-w-7xl items-center justify-between px-4">
                <a href="/dashboard" class="flex items-center gap-2 text-sm font-semibold hover:opacity-80">
                    <AppLogo class="size-5" />
                    <span class="hidden sm:inline">Events</span>
                </a>

                <div class="flex items-center gap-3">
                    <a
                        href="/events"
                        class="flex items-center gap-1.5 text-sm text-muted-foreground transition-colors hover:text-foreground"
                    >
                        <ArrowLeft class="size-4" />
                        <span class="hidden sm:inline">Back to App</span>
                    </a>

                    <button
                        type="button"
                        class="flex size-8 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                        :aria-label="resolvedAppearance === 'dark' ? 'Switch to light mode' : 'Switch to dark mode'"
                        @click="toggleTheme"
                    >
                        <Sun v-if="resolvedAppearance === 'dark'" class="size-4" />
                        <Moon v-else class="size-4" />
                    </button>
                </div>
            </div>
        </header>

        <!-- Page content -->
        <main>
            <slot />
        </main>

        <Toaster />
    </div>
</template>
