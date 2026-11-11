<script setup>
import { Head, Link } from '@inertiajs/vue3';
import SalesLayout from '@/Layouts/SalesLayout.vue';

defineProps({ loans: Object });
</script>

<template>
    <Head title="Riwayat peminjaman" />
    <SalesLayout>
        <h1 class="page-title">Riwayat peminjaman</h1>
        <p class="mt-1 text-nikkou-muted">Semua peminjaman atas nama Anda.</p>

        <ul class="card mt-5 divide-y divide-nikkou-line py-1">
            <li v-for="loan in loans.data" :key="loan.id">
                <Link :href="`/sales/loans/${loan.id}`" class="list-row">
                    <div>
                        <div class="font-medium">{{ loan.borrower }}</div>
                        <div class="text-xs text-nikkou-muted">{{ loan.unit_code }} | {{ loan.type_label }} | Kembali {{ loan.due_on }}</div>
                    </div>
                    <span class="chip" :class="loan.is_active ? 'chip-bad' : 'chip-neutral'">{{ loan.status_label }}</span>
                </Link>
            </li>
            <li v-if="!loans.data.length" class="py-5 text-center text-nikkou-muted">Belum ada peminjaman.</li>
        </ul>

        <div class="mt-4 flex justify-between">
            <Link v-if="loans.prev_page_url" :href="loans.prev_page_url" class="inline-flex min-h-[44px] items-center font-medium text-nikkou-red-deep">Sebelumnya</Link>
            <span v-else></span>
            <Link v-if="loans.next_page_url" :href="loans.next_page_url" class="inline-flex min-h-[44px] items-center font-medium text-nikkou-red-deep">Berikutnya</Link>
        </div>
    </SalesLayout>
</template>
