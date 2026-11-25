<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import NavIcon from '@/Components/NavIcon.vue';
import SalesLayout from '@/Layouts/SalesLayout.vue';

const props = defineProps({
    lead: Object,
    followUps: Array,
    invoices: Array,
    responses: Object,
    channels: Object,
    today: String,
});

const responseChip = { positive: 'chip-neutral', neutral: 'chip-neutral', negative: 'chip-bad' };

const form = useForm({ followed_up_on: props.today, channel: 'whatsapp', response: '', notes: '' });

const submit = () => form.post(`/sales/leads/${props.lead.id}/followups`, {
    preserveScroll: true,
    onSuccess: () => form.reset('response', 'notes'),
});
</script>

<template>
    <Head :title="lead.name" />
    <SalesLayout>
        <Link href="/sales/leads" class="back-link"><NavIcon name="back" :size="18" />Lead saya</Link>

        <div class="mt-1 flex items-start justify-between gap-3">
            <div class="min-w-0">
                <div class="text-xs text-nikkou-muted">{{ lead.code }} | {{ lead.path_label }}</div>
                <h1 class="page-title">{{ lead.name }}</h1>
                <div class="text-xs text-nikkou-muted">{{ lead.location || '-' }}<template v-if="lead.gender"> | {{ lead.gender }}</template><template v-if="lead.age_range"> | {{ lead.age_range }} th</template></div>
            </div>
            <span class="chip flex-none" :class="lead.status === 'cancelled' ? 'chip-bad' : 'chip-neutral'">{{ lead.status_label }}</span>
        </div>

        <dl class="card mt-4 grid grid-cols-2 gap-x-4 gap-y-2 p-4 text-sm">
            <div><dt class="text-xs text-nikkou-muted">{{ lead.path === 'direct' ? 'Produk dibeli' : 'Mencoba' }}</dt><dd>{{ lead.product || '-' }}</dd></div>
            <div v-if="lead.path === 'trial'"><dt class="text-xs text-nikkou-muted">Mulai coba</dt><dd>{{ lead.trial_start || '-' }}</dd></div>
            <div class="col-span-2">
                <dt class="text-xs text-nikkou-muted">{{ lead.path === 'direct' ? 'Aksesoris (masuk invoice)' : 'Aksesoris dibawa saat trial' }}</dt>
                <dd><template v-if="lead.accessories.length">{{ lead.accessories.join(', ') }}</template><template v-else>-</template></dd>
            </div>
            <div><dt class="text-xs text-nikkou-muted">Form gejala awal</dt><dd>{{ lead.symptom_form_filled ? 'Sudah' : 'Belum' }}</dd></div>
            <div><dt class="text-xs text-nikkou-muted">Follow-up</dt><dd>{{ lead.followup_count }} kali</dd></div>
            <div class="col-span-2"><dt class="text-xs text-nikkou-muted">Overall response (follow-up terakhir)</dt><dd><span v-if="lead.response" class="chip" :class="responseChip[lead.response]">{{ lead.response_label }}</span><template v-else>Belum ada follow-up</template></dd></div>
        </dl>
        <Link :href="`/sales/leads/${lead.id}/edit`" class="btn-outline mt-3 w-full text-center">Ubah data lead</Link>

        <h2 class="section-title mt-6">Follow-up ({{ lead.followup_count }} kali)</h2>
        <form novalidate class="card p-4" @submit.prevent="submit">
            <label for="followed_up_on" class="field-label">Tanggal</label>
            <input id="followed_up_on" v-model="form.followed_up_on" type="date" :max="today" class="field-input" />
            <p v-if="form.errors.followed_up_on" class="field-error" role="alert">{{ form.errors.followed_up_on }}</p>

            <label for="channel" class="field-label">Lewat</label>
            <select id="channel" v-model="form.channel" class="field-input">
                <option v-for="(label, value) in channels" :key="value" :value="value">{{ label }}</option>
            </select>

            <label class="field-label">Response pelanggan</label>
            <div class="segmented" role="radiogroup" aria-label="Response pelanggan">
                <button v-for="(label, value) in responses" :key="value" type="button" role="radio" :aria-checked="form.response === value" @click="form.response = value">{{ label }}</button>
            </div>
            <p v-if="form.errors.response" class="field-error" role="alert">{{ form.errors.response }}</p>

            <label for="fu_notes" class="field-label">Catatan pemakaian</label>
            <textarea id="fu_notes" v-model="form.notes" rows="2" class="field-input"></textarea>

            <button type="submit" class="btn-primary mt-4 w-full" :disabled="form.processing">Tambah follow-up</button>
        </form>

        <ul class="mt-3 flex flex-col gap-2">
            <li v-for="f in followUps" :key="f.id" class="card p-3 text-sm">
                <div class="flex items-center justify-between gap-2">
                    <span class="font-medium">{{ f.date }} | {{ f.channel }}</span>
                    <span class="chip" :class="responseChip[f.response]">{{ f.response_label }}</span>
                </div>
                <p v-if="f.notes" class="mt-1 text-nikkou-muted">{{ f.notes }}</p>
                <p class="mt-1 text-xs text-nikkou-muted">Oleh {{ f.by }}</p>
            </li>
            <li v-if="!followUps.length" class="card p-4 text-center text-nikkou-muted">Belum ada follow-up.</li>
        </ul>

        <h2 class="section-title mt-6">Invoice</h2>
        <ul class="flex flex-col gap-2 pb-6">
            <li v-for="i in invoices" :key="i.id" class="card flex items-center justify-between gap-3 p-3 text-sm">
                <div><div class="font-medium">{{ i.code }}</div><div class="text-xs text-nikkou-muted">{{ i.issued_on }} | {{ i.total }}</div></div>
                <span class="chip" :class="i.status === 'cancelled' ? 'chip-bad' : 'chip-neutral'">{{ i.status_label }}</span>
            </li>
            <li v-if="!invoices.length" class="card p-4 text-center text-nikkou-muted">Belum ada invoice.</li>
        </ul>
    </SalesLayout>
</template>
