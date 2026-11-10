<script setup>
import { Head } from '@inertiajs/vue3';
import SalesLayout from '@/Layouts/SalesLayout.vue';

defineProps({
    today: String,
    firstName: String,
    stats: Object,
    loans: Array,
});
</script>

<template>
    <Head title="Beranda" />
    <SalesLayout>
        <h1 class="text-[28px] leading-snug text-nikkou-red-deep">Halo, {{ firstName }}</h1>
        <div class="mt-1 text-nikkou-muted">{{ today }}</div>

        <div class="mt-5 flex gap-3">
            <div class="flex-1 bg-nikkou-mist p-3.5">
                <div class="text-[32px] leading-tight">{{ stats.active }}</div>
                <div class="mt-1 text-xs text-nikkou-muted">Unit sedang dipinjamkan</div>
            </div>
            <div class="flex-1 bg-nikkou-rose p-3.5 text-nikkou-red-ink">
                <div class="text-[32px] leading-tight">{{ stats.due_today }}</div>
                <div class="mt-1 text-xs">Harus kembali hari ini</div>
            </div>
        </div>

        <div class="mt-7 mb-2 flex items-baseline justify-between">
            <h2 class="text-lg">Peminjaman saya</h2>
        </div>

        <ul class="border-t border-nikkou-ink">
            <li v-for="loan in loans" :key="loan.id" class="flex justify-between gap-3 border-b border-nikkou-line py-3">
                <div>
                    <div>{{ loan.borrower }}</div>
                    <div class="text-xs text-nikkou-muted">{{ loan.unit_code }} | {{ loan.type_label }} | Kembali {{ loan.due_on }}</div>
                </div>
                <span class="chip self-start" :class="loan.is_active ? 'chip-bad' : 'chip-neutral'">{{ loan.status_label }}</span>
            </li>
            <li v-if="!loans.length" class="border-b border-nikkou-line py-4 text-nikkou-muted">Belum ada peminjaman.</li>
        </ul>
    </SalesLayout>
</template>
