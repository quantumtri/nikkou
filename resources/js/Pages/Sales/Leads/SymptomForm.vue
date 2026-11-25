<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import NavIcon from '@/Components/NavIcon.vue';
import SalesLayout from '@/Layouts/SalesLayout.vue';

const props = defineProps({
    lead: Object,
    values: Object,
    groups: Array,
    genders: Object,
    units: Array,
});

const form = useForm({ ...props.values });

const submit = () => form.put(`/sales/leads/${props.lead.id}/symptom-form`, { preserveScroll: true });

const hasCancer = () => (form.conditions.tumor ?? []).includes('had_cancer');
const err = (key) => form.errors[key];
</script>

<template>
    <Head title="Form gejala awal" />
    <SalesLayout>
        <Link :href="`/sales/leads/${lead.id}`" class="back-link"><NavIcon name="back" :size="18" />{{ lead.name }}</Link>
        <h1 class="page-title mt-1">Form gejala awal</h1>
        <p class="text-xs text-nikkou-muted">{{ lead.code }} | Salin dari formulir cetak (halaman 1-2)</p>

        <form novalidate class="mt-4 pb-8" @submit.prevent="submit">
            <h2 class="section-title"><span class="step">1</span>Diisi oleh tim sales</h2>
            <label for="activity_on" class="field-label">Tanggal kegiatan</label>
            <input id="activity_on" v-model="form.activity_on" type="date" class="field-input" />
            <label for="session_location" class="field-label">Sesi / lokasi</label>
            <input id="session_location" v-model="form.session_location" class="field-input" autocomplete="off" />
            <label for="unit_id" class="field-label">No. seri mesin (ID inventori)</label>
            <select id="unit_id" v-model="form.unit_id" class="field-input">
                <option value="">Belum dipilih</option>
                <option v-for="u in units" :key="u.id" :value="u.id">{{ u.code }}</option>
            </select>
            <p v-if="err('unit_id')" class="field-error" role="alert">{{ err('unit_id') }}</p>
            <label for="cup_serial" class="field-label">No. seri gelas air</label>
            <input id="cup_serial" v-model="form.cup_serial" class="field-input" autocomplete="off" />
            <label for="machine_taken_on" class="field-label">Tgl. mesin dibawa pulang</label>
            <input id="machine_taken_on" v-model="form.machine_taken_on" type="date" class="field-input" />
            <label for="filled_on" class="field-label">Tanggal form</label>
            <input id="filled_on" v-model="form.filled_on" type="date" class="field-input" />
            <p v-if="err('filled_on')" class="field-error" role="alert">{{ err('filled_on') }}</p>

            <h2 class="section-title"><span class="step">2</span>Data diri</h2>
            <label for="name" class="field-label">Nama</label>
            <input id="name" v-model="form.name" class="field-input" autocomplete="off" />
            <p v-if="err('name')" class="field-error" role="alert">{{ err('name') }}</p>

            <label class="field-label">Jenis kelamin</label>
            <div class="segmented" role="radiogroup" aria-label="Jenis kelamin">
                <button v-for="(label, value) in genders" :key="value" type="button" role="radio" :aria-checked="form.gender === value" @click="form.gender = value">{{ label }}</button>
            </div>
            <p v-if="err('gender')" class="field-error" role="alert">{{ err('gender') }}</p>

            <label for="birth_date" class="field-label">Tanggal lahir</label>
            <input id="birth_date" v-model="form.birth_date" type="date" class="field-input" />
            <p v-if="err('birth_date')" class="field-error" role="alert">{{ err('birth_date') }}</p>
            <label for="whatsapp_number" class="field-label">No. HP / WhatsApp</label>
            <input id="whatsapp_number" v-model="form.whatsapp_number" inputmode="tel" class="field-input" autocomplete="off" />
            <p v-if="err('whatsapp_number')" class="field-error" role="alert">{{ err('whatsapp_number') }}</p>
            <label for="referred_by" class="field-label">Direkomendasikan oleh</label>
            <input id="referred_by" v-model="form.referred_by" class="field-input" autocomplete="off" />
            <label for="email" class="field-label">Email</label>
            <input id="email" v-model="form.email" type="email" class="field-input" autocomplete="off" />
            <p v-if="err('email')" class="field-error" role="alert">{{ err('email') }}</p>
            <label for="address" class="field-label">Alamat</label>
            <input id="address" v-model="form.address" class="field-input" autocomplete="off" />
            <label for="city" class="field-label">Kota</label>
            <input id="city" v-model="form.city" class="field-input" autocomplete="off" />
            <p v-if="err('city')" class="field-error" role="alert">{{ err('city') }}</p>

            <h2 class="section-title"><span class="step">3</span>Persetujuan data</h2>
            <label class="flex min-h-[44px] items-start gap-3">
                <input v-model="form.consent_data" type="checkbox" class="mt-1 h-5 w-5 flex-none" :disabled="lead.consent_given" />
                <span>Saya setuju data diri dan data kesehatan saya dipakai oleh Nikkou untuk peminjaman mesin, saran penggunaan, dan layanan purna jual.</span>
            </label>
            <p v-if="err('consent_data')" class="field-error" role="alert">{{ err('consent_data') }}</p>
            <label class="flex min-h-[44px] items-start gap-3">
                <input v-model="form.consent_whatsapp" type="checkbox" class="mt-1 h-5 w-5 flex-none" />
                <span>Saya bersedia dihubungi lewat WhatsApp untuk tindak lanjut dan survei pengalaman (dapat berhenti kapan saja).</span>
            </label>

            <h2 class="section-title"><span class="step">4</span>Survei kondisi kesehatan</h2>
            <p class="text-sm text-nikkou-muted">Centang kondisi peserta saat ini.</p>
            <fieldset v-for="g in groups" :key="g.key" class="card mt-3 p-4">
                <legend class="px-1 text-sm font-medium text-nikkou-red-deep">{{ g.label }}</legend>
                <label v-for="item in g.items" :key="item.key" class="flex min-h-[44px] items-center gap-3">
                    <input v-model="form.conditions[g.key]" type="checkbox" :value="item.key" class="h-5 w-5 flex-none" />
                    <span>{{ item.label }}</span>
                </label>
                <template v-if="g.has_other">
                    <label :for="`other_${g.key}`" class="field-label">Lainnya</label>
                    <input :id="`other_${g.key}`" v-model="form.other_conditions[g.key]" class="field-input" autocomplete="off" />
                </template>
                <template v-if="g.key === 'tumor' && hasCancer()">
                    <label for="cancer_type" class="field-label">Jenis kanker</label>
                    <input id="cancer_type" v-model="form.cancer_type" class="field-input" autocomplete="off" />
                    <label for="cancer_treatment" class="field-label">Status pengobatan</label>
                    <input id="cancer_treatment" v-model="form.cancer_treatment" class="field-input" autocomplete="off" />
                </template>
                <p v-if="err(`conditions.${g.key}`)" class="field-error" role="alert">{{ err(`conditions.${g.key}`) }}</p>
            </fieldset>

            <label for="sales_notes" class="field-label">Catatan sales</label>
            <textarea id="sales_notes" v-model="form.sales_notes" rows="3" class="field-input"></textarea>

            <button type="submit" class="btn-primary mt-6 w-full" :disabled="form.processing">Simpan form gejala</button>
        </form>
    </SalesLayout>
</template>
