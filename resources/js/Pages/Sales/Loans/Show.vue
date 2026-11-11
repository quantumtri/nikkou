<script setup>
import { Head, Link } from '@inertiajs/vue3';
import NavIcon from '@/Components/NavIcon.vue';
import SalesLayout from '@/Layouts/SalesLayout.vue';

defineProps({
    loan: Object,
    whatsappUrl: String,
});
</script>

<template>
    <Head :title="`Peminjaman ${loan.unit_code}`" />
    <SalesLayout>
        <Link href="/sales/loans" class="back-link"><NavIcon name="back" :size="18" />Riwayat</Link>
        <h1 class="page-title mt-1">Peminjaman {{ loan.unit_code }}</h1>
        <span class="chip mt-2" :class="loan.is_active ? 'chip-bad' : 'chip-neutral'">{{ loan.status_label }}</span>

        <h2 class="section-title">Ringkasan</h2>
        <dl class="card mt-3 divide-y divide-nikkou-line py-1">
            <div class="flex gap-4 py-2.5"><dt class="w-28 flex-none text-nikkou-muted">Jenis</dt><dd>{{ loan.type_label }}</dd></div>
            <div class="flex gap-4 py-2.5"><dt class="w-28 flex-none text-nikkou-muted">Unit</dt><dd>{{ loan.unit_code }} | {{ loan.unit_type }}</dd></div>
            <div v-if="loan.event_name" class="flex gap-4 py-2.5"><dt class="w-28 flex-none text-nikkou-muted">Event</dt><dd>{{ loan.event_name }}</dd></div>
            <template v-if="loan.customer">
                <div class="flex gap-4 py-2.5"><dt class="w-28 flex-none text-nikkou-muted">Pelanggan</dt><dd>{{ loan.customer.name }}</dd></div>
                <div class="flex gap-4 py-2.5"><dt class="w-28 flex-none text-nikkou-muted">WhatsApp</dt><dd>+{{ loan.customer.whatsapp_number }}</dd></div>
                <div class="flex gap-4 py-2.5">
                    <dt class="w-28 flex-none text-nikkou-muted">Alamat</dt>
                    <dd>{{ [loan.customer.address, loan.customer.district, loan.customer.city].filter(Boolean).join(', ') || '-' }}</dd>
                </div>
            </template>
            <div class="flex gap-4 py-2.5"><dt class="w-28 flex-none text-nikkou-muted">Mulai</dt><dd>{{ loan.starts_on }}</dd></div>
            <div class="flex gap-4 py-2.5"><dt class="w-28 flex-none text-nikkou-muted">Kembali</dt><dd>{{ loan.due_on_long }}</dd></div>
            <div class="flex gap-4 py-2.5"><dt class="w-28 flex-none text-nikkou-muted">Sales</dt><dd>{{ loan.sales }}</dd></div>
            <div v-if="loan.override_reason" class="flex gap-4 py-2.5">
                <dt class="w-28 flex-none text-nikkou-muted">Override admin</dt>
                <dd>{{ loan.override_reason }}</dd>
            </div>
        </dl>

        <div class="mt-7 flex flex-col gap-3">
            <a :href="whatsappUrl" target="_blank" rel="noopener" class="btn-outline">Kirim ringkasan ke WhatsApp</a>
            <Link href="/sales" class="btn-primary">Selesai</Link>
        </div>
    </SalesLayout>
</template>
