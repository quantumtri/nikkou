<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import NavIcon from '@/Components/NavIcon.vue';
import SalesLayout from '@/Layouts/SalesLayout.vue';

const props = defineProps({
    lead: Object,
    genders: Object,
    ageRanges: Object,
    statuses: Object,
    cancelReasons: Object,
    responses: Object,
    provinces: Array,
    products: Array,
    today: String,
});

const editing = computed(() => !!props.lead);

const form = useForm({
    name: props.lead?.name ?? '',
    whatsapp_number: props.lead?.whatsapp_number ?? '',
    city: props.lead?.city ?? '',
    province: props.lead?.province ?? '',
    gender: props.lead?.gender ?? '',
    age_range: props.lead?.age_range ?? '',
    product_id: props.lead?.product_id ?? '',
    trial_started_on: props.lead?.trial_started_on ?? props.today,
    symptom_form_filled: props.lead?.symptom_form_filled ?? false,
    status: props.lead?.status ?? 'new',
    cancel_reason: props.lead?.cancel_reason ?? '',
    followup_count: props.lead?.followup_count ?? 0,
    overall_response: props.lead?.overall_response ?? '',
    notes: props.lead?.notes ?? '',
});

const groups = computed(() => {
    const out = {};
    for (const p of props.products) (out[p.category] ??= []).push(p);
    return out;
});

const submit = () => (editing.value ? form.put(`/sales/leads/${props.lead.id}`) : form.post('/sales/leads'));
</script>

<template>
    <Head :title="editing ? 'Ubah lead' : 'Tambah lead'" />
    <SalesLayout>
        <Link href="/sales/leads" class="back-link"><NavIcon name="back" :size="18" />Lead saya</Link>
        <h1 class="page-title mt-1">{{ editing ? 'Ubah lead' : 'Tambah lead' }}</h1>

        <form novalidate class="mt-4" @submit.prevent="submit">
            <h2 class="section-title"><span class="step">1</span>Calon pelanggan</h2>
            <label for="name" class="field-label">Nama</label>
            <input id="name" v-model="form.name" class="field-input" autocomplete="off" />
            <p v-if="form.errors.name" class="field-error" role="alert">{{ form.errors.name }}</p>

            <label for="whatsapp_number" class="field-label">WhatsApp (opsional)</label>
            <input id="whatsapp_number" v-model="form.whatsapp_number" class="field-input" inputmode="tel" placeholder="08xx xxxx xxxx" autocomplete="off" />
            <p v-if="form.errors.whatsapp_number" class="field-error" role="alert">{{ form.errors.whatsapp_number }}</p>

            <div class="flex gap-3">
                <div class="flex-1">
                    <label for="city" class="field-label">Kota</label>
                    <input id="city" v-model="form.city" class="field-input" autocomplete="off" />
                    <p v-if="form.errors.city" class="field-error" role="alert">{{ form.errors.city }}</p>
                </div>
                <div class="flex-1">
                    <label for="province" class="field-label">Provinsi</label>
                    <select id="province" v-model="form.province" class="field-input">
                        <option value="">Pilih</option>
                        <option v-for="p in provinces" :key="p" :value="p">{{ p }}</option>
                    </select>
                    <p v-if="form.errors.province" class="field-error" role="alert">{{ form.errors.province }}</p>
                </div>
            </div>

            <label class="field-label">Gender</label>
            <div class="segmented" role="radiogroup" aria-label="Gender">
                <button v-for="(label, value) in genders" :key="value" type="button" role="radio" :aria-checked="form.gender === value" @click="form.gender = value">{{ label }}</button>
            </div>
            <p v-if="form.errors.gender" class="field-error" role="alert">{{ form.errors.gender }}</p>

            <label for="age_range" class="field-label">Range usia</label>
            <select id="age_range" v-model="form.age_range" class="field-input">
                <option value="">Pilih</option>
                <option v-for="(label, value) in ageRanges" :key="value" :value="value">{{ label }}</option>
            </select>
            <p v-if="form.errors.age_range" class="field-error" role="alert">{{ form.errors.age_range }}</p>

            <h2 class="section-title"><span class="step">2</span>Trial</h2>
            <label for="product_id" class="field-label">Mencoba produk apa</label>
            <select id="product_id" v-model="form.product_id" class="field-input">
                <option value="">Pilih produk</option>
                <optgroup v-for="(items, category) in groups" :key="category" :label="category">
                    <option v-for="p in items" :key="p.id" :value="p.id">{{ p.name }}</option>
                </optgroup>
            </select>
            <p v-if="form.errors.product_id" class="field-error" role="alert">{{ form.errors.product_id }}</p>

            <label for="trial_started_on" class="field-label">Mulai coba tanggal</label>
            <input id="trial_started_on" v-model="form.trial_started_on" type="date" class="field-input" />
            <p v-if="form.errors.trial_started_on" class="field-error" role="alert">{{ form.errors.trial_started_on }}</p>

            <label class="mt-4 flex min-h-[44px] items-center gap-3">
                <input v-model="form.symptom_form_filled" type="checkbox" class="h-5 w-5" />
                <span>Sudah isi form gejala awal</span>
            </label>

            <h2 class="section-title"><span class="step">3</span>Status</h2>
            <label for="status" class="field-label">Status lead</label>
            <select id="status" v-model="form.status" class="field-input">
                <option v-for="(label, value) in statuses" :key="value" :value="value">{{ label }}</option>
            </select>
            <p v-if="form.errors.status" class="field-error" role="alert">{{ form.errors.status }}</p>

            <template v-if="form.status === 'cancelled'">
                <label for="cancel_reason" class="field-label">Alasan batal</label>
                <select id="cancel_reason" v-model="form.cancel_reason" class="field-input">
                    <option value="">Pilih alasan</option>
                    <option v-for="(label, value) in cancelReasons" :key="value" :value="value">{{ label }}</option>
                </select>
                <p v-if="form.errors.cancel_reason" class="field-error" role="alert">{{ form.errors.cancel_reason }}</p>
            </template>

            <label for="followup_count" class="field-label">Follow-up pemakaian (berapa kali)</label>
            <input id="followup_count" v-model="form.followup_count" type="number" min="0" inputmode="numeric" class="field-input" />
            <p v-if="form.errors.followup_count" class="field-error" role="alert">{{ form.errors.followup_count }}</p>

            <label for="overall_response" class="field-label">Overall response</label>
            <select id="overall_response" v-model="form.overall_response" class="field-input">
                <option value="">Belum ada</option>
                <option v-for="(label, value) in responses" :key="value" :value="value">{{ label }}</option>
            </select>

            <label for="notes" class="field-label">Catatan</label>
            <textarea id="notes" v-model="form.notes" rows="2" class="field-input"></textarea>

            <button type="submit" class="btn-primary mt-6 w-full" :disabled="form.processing">{{ editing ? 'Simpan perubahan' : 'Simpan lead' }}</button>
        </form>
    </SalesLayout>
</template>
