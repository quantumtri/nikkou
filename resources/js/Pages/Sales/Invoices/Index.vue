<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import NavIcon from '@/Components/NavIcon.vue';
import SalesLayout from '@/Layouts/SalesLayout.vue';

const props = defineProps({ invoices: Array });

const filters = [
    { value: '', label: 'Semua' },
    { value: 'unpaid', label: 'Belum dibayar' },
    { value: 'paid', label: 'Lunas' },
    { value: 'cancelled', label: 'Batal' },
];
const filter = ref('');
const shown = computed(() => props.invoices.filter((i) => !filter.value || i.status === filter.value));
const chip = { unpaid: 'chip-neutral', paid: 'chip-neutral', cancelled: 'chip-bad' };
</script>

<template>
    <Head title="Invoice saya" />
    <SalesLayout>
        <Link href="/sales" class="back-link"><NavIcon name="back" :size="18" />Beranda</Link>
        <h1 class="page-title mt-1">Invoice saya</h1>
        <p class="mt-1 text-sm text-nikkou-muted">Invoice dari lead Anda. Admin yang menandai lunas.</p>

        <div class="segmented mt-4" role="radiogroup" aria-label="Status invoice">
            <button v-for="f in filters" :key="f.value" type="button" role="radio" :aria-checked="filter === f.value" @click="filter = f.value">{{ f.label }}</button>
        </div>

        <ul class="mt-4 flex flex-col gap-3 pb-6">
            <li v-for="i in shown" :key="i.id">
                <Link :href="`/sales/leads/${i.lead_id}`" class="card block p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="text-xs text-nikkou-muted">{{ i.code }} | {{ i.lead_code }}</div>
                            <div class="font-medium">{{ i.customer }}</div>
                        </div>
                        <span class="chip flex-none" :class="chip[i.status]">{{ i.status_label }}</span>
                    </div>
                    <ul class="mt-3 text-sm">
                        <li v-for="item in i.items" :key="item.id">
                            {{ item.label }}<span v-if="item.unit_code" class="ml-1 text-xs text-nikkou-muted">({{ item.unit_code }})</span>
                        </li>
                    </ul>
                    <div class="mt-3 flex items-center justify-between text-sm">
                        <span class="text-xs text-nikkou-muted">{{ i.paid_on ? `Lunas ${i.paid_on}` : `Terbit ${i.issued_on}` }}</span>
                        <span class="font-medium">{{ i.total }}</span>
                    </div>
                </Link>
            </li>
            <li v-if="!shown.length" class="card p-5 text-center text-nikkou-muted">Belum ada invoice.</li>
        </ul>
    </SalesLayout>
</template>
