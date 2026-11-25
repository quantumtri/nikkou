<script setup>
import { Head, Link } from '@inertiajs/vue3';
import NavIcon from '@/Components/NavIcon.vue';
import SalesLayout from '@/Layouts/SalesLayout.vue';

defineProps({ leads: Array });

const responseChip = { positive: 'chip-neutral', neutral: 'chip-neutral', negative: 'chip-bad' };
</script>

<template>
    <Head title="Lead saya" />
    <SalesLayout>
        <Link href="/sales" class="back-link"><NavIcon name="back" :size="18" />Beranda</Link>
        <div class="mt-1 flex items-center justify-between gap-3">
            <h1 class="page-title">Lead saya</h1>
        </div>
        <Link href="/sales/leads/create" class="btn-primary mt-4">Tambah lead</Link>

        <ul class="mt-5 flex flex-col gap-3">
            <li v-for="lead in leads" :key="lead.id">
                <Link :href="`/sales/leads/${lead.id}`" class="card block p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="text-xs text-nikkou-muted">{{ lead.code }} | {{ lead.path_label }}</div>
                            <div class="font-medium">{{ lead.name }}</div>
                            <div class="text-xs text-nikkou-muted">{{ lead.location || '-' }}<template v-if="lead.gender"> | {{ lead.gender }}</template><template v-if="lead.age_range"> | {{ lead.age_range }} th</template></div>
                        </div>
                        <span class="chip flex-none" :class="lead.status === 'cancelled' ? 'chip-bad' : 'chip-neutral'">{{ lead.status_label }}</span>
                    </div>
                    <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-1 text-xs">
                        <div><dt class="text-nikkou-muted">{{ lead.path_label === 'Trial' ? 'Mencoba' : 'Membeli' }}</dt><dd>{{ lead.product || '-' }}</dd></div>
                        <div><dt class="text-nikkou-muted">Mulai coba</dt><dd>{{ lead.trial_start || '-' }}</dd></div>
                        <div><dt class="text-nikkou-muted">Form gejala awal</dt><dd>{{ lead.symptom_form_filled ? 'Sudah' : 'Belum' }}</dd></div>
                        <div><dt class="text-nikkou-muted">Follow-up</dt><dd>{{ lead.followup_count }} kali</dd></div>
                        <div><dt class="text-nikkou-muted">Overall response</dt><dd><span v-if="lead.response" class="chip" :class="responseChip[lead.response]">{{ lead.response_label }}</span><template v-else>-</template></dd></div>
                    </dl>
                </Link>
            </li>
            <li v-if="!leads.length" class="card p-5 text-center text-nikkou-muted">Belum ada lead. Mulai dari tombol "Tambah lead".</li>
        </ul>
    </SalesLayout>
</template>
