<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import NavIcon from '@/Components/NavIcon.vue';

defineProps({
    showNav: { type: Boolean, default: true },
});

const page = usePage();
const user = computed(() => page.props.auth.user);
const nav = computed(() => page.props.nav ?? []);
</script>

<template>
    <div class="mx-auto min-h-screen w-full max-w-[480px] bg-white" :class="showNav && nav.length ? 'pb-24' : ''">
        <header class="sticky top-0 z-30 flex items-center justify-between border-b border-nikkou-line bg-white px-5 py-3">
            <img src="/images/logo-wordmark.png" alt="NIKKOU" width="72" height="18" class="block h-[18px] w-auto" />
            <div v-if="user" class="flex items-center gap-2 text-sm">
                <span class="rounded-full bg-nikkou-paper px-3 py-1.5 text-nikkou-muted">{{ user.role_label }}: {{ user.first_name }}</span>
                <Link href="/logout" method="post" as="button" class="min-h-[44px] px-1 font-medium text-nikkou-red-deep">Keluar</Link>
            </div>
        </header>

        <main class="px-5 py-6">
            <div v-if="page.props.flash?.success" role="status" class="notice mb-5">
                {{ page.props.flash.success }}
            </div>
            <slot />
        </main>

        <nav
            v-if="showNav && nav.length"
            aria-label="Menu utama"
            class="fixed inset-x-0 bottom-0 z-30 mx-auto flex w-full max-w-[480px] border-t border-nikkou-line bg-white px-2 pt-1.5 pb-[max(0.375rem,env(safe-area-inset-bottom))]"
        >
            <Link
                v-for="item in nav"
                :key="item.href"
                :href="item.href"
                class="flex min-h-[56px] flex-1 flex-col items-center justify-center gap-0.5 rounded-[14px] text-xs font-medium"
                :class="item.active ? 'bg-nikkou-rose text-nikkou-red-ink' : 'text-nikkou-muted'"
                :aria-current="item.active ? 'page' : undefined"
            >
                <NavIcon :name="item.icon" />
                {{ item.label }}
            </Link>
        </nav>
    </div>
</template>
