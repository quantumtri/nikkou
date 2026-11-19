<script setup>
import { Head, Link } from '@inertiajs/vue3';
import NavIcon from '@/Components/NavIcon.vue';
import SalesLayout from '@/Layouts/SalesLayout.vue';

defineProps({ sops: Array });
</script>

<template>
    <Head title="SOP" />
    <SalesLayout>
        <Link href="/sales" class="back-link"><NavIcon name="back" :size="18" />Beranda</Link>
        <h1 class="page-title mt-1">SOP</h1>
        <p class="mt-1 text-sm text-nikkou-muted">Prosedur kerja yang berlaku untuk Anda.</p>

        <ul class="card mt-4 divide-y divide-nikkou-line py-1">
            <li v-for="sop in sops" :key="sop.id">
                <Link :href="`/sales/sops/${sop.id}`" class="list-row">
                    <div>
                        <div class="font-medium">{{ sop.title }}</div>
                        <div class="text-xs text-nikkou-muted">{{ sop.code }} | Versi {{ sop.version }}<template v-if="sop.effective_from"> | Berlaku {{ sop.effective_from }}</template></div>
                    </div>
                    <span class="chip" :class="sop.is_read ? 'chip-neutral' : 'chip-bad'">{{ sop.is_read ? 'Sudah dibaca' : 'Belum dibaca' }}</span>
                </Link>
            </li>
            <li v-if="!sops.length" class="py-5 text-center text-nikkou-muted">Belum ada SOP yang ditugaskan kepada Anda.</li>
        </ul>
    </SalesLayout>
</template>
