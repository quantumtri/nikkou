<script setup>
import { Head, Link } from '@inertiajs/vue3';
import SalesLayout from '@/Layouts/SalesLayout.vue';

defineProps({ loans: Object });
</script>

<template>
    <Head title="Riwayat peminjaman" />
    <SalesLayout>
        <h1 class="text-[28px] leading-snug text-nikkou-red-deep">Riwayat peminjaman</h1>
        <p class="text-nikkou-muted">Semua peminjaman atas nama Anda.</p>

        <ul class="mt-5 border-t border-nikkou-ink">
            <li v-for="loan in loans.data" :key="loan.id" class="border-b border-nikkou-line">
                <Link :href="`/sales/loans/${loan.id}`" class="flex min-h-[56px] justify-between gap-3 py-3">
                    <div>
                        <div>{{ loan.borrower }}</div>
                        <div class="text-xs text-nikkou-muted">{{ loan.unit_code }} | {{ loan.type_label }} | Kembali {{ loan.due_on }}</div>
                    </div>
                    <span class="chip self-start" :class="loan.is_active ? 'chip-bad' : 'chip-neutral'">{{ loan.status_label }}</span>
                </Link>
            </li>
            <li v-if="!loans.data.length" class="border-b border-nikkou-line py-4 text-nikkou-muted">Belum ada peminjaman.</li>
        </ul>

        <div class="mt-4 flex justify-between">
            <Link v-if="loans.prev_page_url" :href="loans.prev_page_url" class="min-h-[44px] text-nikkou-red-deep underline">Sebelumnya</Link>
            <span v-else></span>
            <Link v-if="loans.next_page_url" :href="loans.next_page_url" class="min-h-[44px] text-nikkou-red-deep underline">Berikutnya</Link>
        </div>
    </SalesLayout>
</template>
