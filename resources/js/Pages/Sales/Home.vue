<script setup>
import { Head, Link } from '@inertiajs/vue3';
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
        <section class="rounded-[28px] bg-nikkou-red-deep p-5 text-white">
            <h1 class="text-[28px] leading-tight font-semibold tracking-[-0.01em]">Halo, {{ firstName }}</h1>
            <p class="mt-1 text-sm">{{ today }}</p>

            <div class="mt-5 flex gap-3">
                <div class="flex-1 rounded-[20px] bg-white p-4 text-nikkou-ink">
                    <div class="text-[32px] leading-none font-semibold tabular-nums">{{ stats.active }}</div>
                    <div class="mt-2 text-xs text-nikkou-muted">Unit sedang dipinjamkan</div>
                </div>
                <div class="flex-1 rounded-[20px] bg-nikkou-rose p-4 text-nikkou-red-ink">
                    <div class="text-[32px] leading-none font-semibold tabular-nums">{{ stats.due_today }}</div>
                    <div class="mt-2 text-xs">Harus kembali hari ini</div>
                </div>
            </div>
        </section>

        <div class="mt-5 flex flex-col gap-3">
            <Link href="/sales/loans/create" class="btn-primary">Pinjamkan unit</Link>
        </div>

        <div class="mt-8 flex items-center justify-between">
            <h2 class="text-[17px] font-semibold">Peminjaman saya</h2>
            <Link href="/sales/loans" class="inline-flex min-h-[44px] items-center text-sm font-medium text-nikkou-red-deep">Lihat semua</Link>
        </div>

        <ul class="card divide-y divide-nikkou-line py-1">
            <li v-for="loan in loans" :key="loan.id">
                <Link :href="`/sales/loans/${loan.id}`" class="list-row">
                    <div>
                        <div class="font-medium">{{ loan.borrower }}</div>
                        <div class="text-xs text-nikkou-muted">{{ loan.unit_code }} | {{ loan.type_label }} | Kembali {{ loan.due_on }}</div>
                    </div>
                    <span class="chip" :class="loan.is_active ? 'chip-bad' : 'chip-neutral'">{{ loan.status_label }}</span>
                </Link>
            </li>
            <li v-if="!loans.length" class="py-5 text-center text-nikkou-muted">Belum ada peminjaman. Mulai dari tombol "Pinjamkan unit".</li>
        </ul>
    </SalesLayout>
</template>
