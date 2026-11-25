<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import NavIcon from '@/Components/NavIcon.vue';
import SalesLayout from '@/Layouts/SalesLayout.vue';

const props = defineProps({
    lead: Object,
    genders: Object,
    ageRanges: Object,
    statuses: Object,
    cancelReasons: Object,
    paths: Object,
    provinces: Array,
    products: Array,
    accessoryProducts: Array,
    unitsByProduct: Object,
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
    path: props.lead?.path ?? 'trial',
    product_id: props.lead?.product_id ?? '',
    unit_id: '',
    accessories: (props.lead?.accessories ?? []).map((a) => ({ ...a })),
    trial_started_on: props.lead?.trial_started_on ?? props.today,
    symptom_form_filled: props.lead?.symptom_form_filled ?? false,
    status: props.lead?.status ?? 'new',
    cancel_reason: props.lead?.cancel_reason ?? '',
    notes: props.lead?.notes ?? '',
});

const direct = computed(() => form.path === 'direct');
const units = computed(() => props.unitsByProduct?.[form.product_id] ?? []);
watch(() => form.product_id, () => { form.unit_id = ''; });

const accessoryQty = (id) => form.accessories.find((a) => a.product_id === id)?.quantity ?? 0;
const setAccessory = (id, qty) => {
    const q = Math.max(0, Math.min(99, Number(qty) || 0));
    const rest = form.accessories.filter((a) => a.product_id !== id);
    form.accessories = q > 0 ? [...rest, { product_id: id, quantity: q }] : rest;
};

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

            <h2 class="section-title"><span class="step">2</span>Produk</h2>
            <label class="field-label">Jalur</label>
            <div class="segmented" role="radiogroup" aria-label="Jalur">
                <button v-for="(label, value) in paths" :key="value" type="button" role="radio" :aria-checked="form.path === value" :disabled="editing" @click="form.path = value">{{ label }}</button>
            </div>
            <p class="mt-1 text-xs text-nikkou-muted">{{ direct ? 'Beli langsung: invoice dibuat otomatis.' : 'Trial: dicoba dulu sebelum membeli.' }}</p>
            <p v-if="form.errors.path" class="field-error" role="alert">{{ form.errors.path }}</p>

            <label for="product_id" class="field-label">{{ direct ? 'Produk yang dibeli' : 'Mencoba produk apa' }}</label>
            <select id="product_id" v-model="form.product_id" class="field-input">
                <option value="">Pilih produk utama</option>
                <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }}</option>
            </select>
            <p v-if="form.errors.product_id" class="field-error" role="alert">{{ form.errors.product_id }}</p>

            <template v-if="direct && !editing">
                <label for="unit_id" class="field-label">ID inventori yang dijual</label>
                <select id="unit_id" v-model="form.unit_id" class="field-input" :disabled="!form.product_id">
                    <option value="">{{ form.product_id ? (units.length ? 'Pilih ID inventori' : 'Tidak ada unit Tersedia') : 'Pilih produk dulu' }}</option>
                    <option v-for="u in units" :key="u.id" :value="u.id">{{ u.label }}</option>
                </select>
                <p class="mt-1 text-xs text-nikkou-muted">Unit fisik dari gudang. Menjadi Terjual saat invoice lunas.</p>
                <p v-if="form.errors.unit_id" class="field-error" role="alert">{{ form.errors.unit_id }}</p>
            </template>

            <template v-if="!direct">
                <label for="trial_started_on" class="field-label">Mulai coba tanggal</label>
                <input id="trial_started_on" v-model="form.trial_started_on" type="date" class="field-input" />
                <p v-if="form.errors.trial_started_on" class="field-error" role="alert">{{ form.errors.trial_started_on }}</p>
            </template>

            <label class="field-label">{{ direct ? 'Aksesoris (masuk invoice)' : 'Aksesoris dibawa saat trial' }}</label>
            <ul class="flex flex-col gap-2">
                <li v-for="a in accessoryProducts" :key="a.id" class="flex min-h-[44px] items-center justify-between gap-3">
                    <span>{{ a.name }} <span class="text-xs text-nikkou-muted">{{ a.price }}</span></span>
                    <span class="flex items-center gap-2">
                        <button type="button" class="chip chip-neutral" :aria-label="`Kurangi ${a.name}`" @click="setAccessory(a.id, accessoryQty(a.id) - 1)">-</button>
                        <span class="w-6 text-center" aria-live="polite">{{ accessoryQty(a.id) }}</span>
                        <button type="button" class="chip chip-neutral" :aria-label="`Tambah ${a.name}`" @click="setAccessory(a.id, accessoryQty(a.id) + 1)">+</button>
                    </span>
                </li>
            </ul>
            <p v-if="form.errors.accessories" class="field-error" role="alert">{{ form.errors.accessories }}</p>

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

            <label for="notes" class="field-label">Catatan</label>
            <textarea id="notes" v-model="form.notes" rows="2" class="field-input"></textarea>

            <button type="submit" class="btn-primary mt-6 w-full" :disabled="form.processing">{{ editing ? 'Simpan perubahan' : 'Simpan lead' }}</button>
        </form>
    </SalesLayout>
</template>
