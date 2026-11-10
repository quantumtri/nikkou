<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({
    showNav: { type: Boolean, default: true },
});

const page = usePage();
const user = computed(() => page.props.auth.user);
const nav = computed(() => page.props.nav ?? []);
</script>

<template>
    <div class="mx-auto min-h-screen w-full max-w-[480px] bg-white" :class="showNav && nav.length ? 'pb-16' : ''">
        <header class="flex items-center justify-between border-b border-nikkou-line px-5 py-4">
            <img src="/images/logo-wordmark.png" alt="NIKKOU" class="block h-[18px] w-auto" />
            <div v-if="user" class="flex items-center gap-3 text-sm text-nikkou-muted">
                <span>{{ user.role_label }}: {{ user.first_name }}</span>
                <Link href="/logout" method="post" as="button" class="min-h-[44px] text-nikkou-red-deep underline">Keluar</Link>
            </div>
        </header>

        <main class="px-5 py-5">
            <div
                v-if="page.props.flash?.success"
                role="status"
                class="mb-4 border border-nikkou-blue-ink bg-nikkou-blue-tint px-3.5 py-3 text-nikkou-blue-ink"
            >
                {{ page.props.flash.success }}
            </div>
            <slot />
        </main>

        <nav
            v-if="showNav && nav.length"
            aria-label="Menu utama"
            class="fixed inset-x-0 bottom-0 mx-auto flex w-full max-w-[480px] border-t border-nikkou-ink bg-white"
        >
            <Link
                v-for="item in nav"
                :key="item.href"
                :href="item.href"
                class="-mt-px flex min-h-[56px] flex-1 items-center justify-center border-t-[3px]"
                :class="item.active ? 'border-nikkou-red-deep text-nikkou-red-deep' : 'border-transparent'"
                :aria-current="item.active ? 'page' : undefined"
            >
                {{ item.label }}
            </Link>
        </nav>
    </div>
</template>
