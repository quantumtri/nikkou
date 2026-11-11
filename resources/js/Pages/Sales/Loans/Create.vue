<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import NavIcon from '@/Components/NavIcon.vue';
import UnitScanner from '@/Components/UnitScanner.vue';
import SalesLayout from '@/Layouts/SalesLayout.vue';
import { lookupUnit } from '@/lib/units';

const props = defineProps({
    today: String,
    defaultDue: String,
    types: Object,
    canOverride: Boolean,
    salesName: String,
});

const form = useForm({
    type: 'home_trial',
    unit_code: '',
    customer_name: '',
    whatsapp_number: '',
    address: '',
    city: '',
    district: '',
    starts_on: props.today,
    due_on: props.defaultDue,
    event_name: '',
    consent_data: false,
    consent_whatsapp: false,
    override_reason: '',
});

const unit = ref(null);
const unitError = ref('');
const scanning = ref(false);

const isEvent = computed(() => form.type === 'event');
const blocked = computed(() => unit.value && !unit.value.loanable);
// Sales cannot save a blocked unit; admins can, with a reason.
const canSubmit = computed(() => !form.processing && (!blocked.value || (props.canOverride && form.override_reason.trim() !== '')));

const checkUnit = async () => {
    unitError.value = '';
    form.clearErrors('unit_code');

    if (!form.unit_code.trim()) {
        unit.value = null;
        return;
    }

    try {
        unit.value = await lookupUnit(form.unit_code);
        form.unit_code = unit.value.code;
    } catch (e) {
        unit.value = null;
        unitError.value = e.message;
    }
};

const useScanned = (scanned) => {
    unit.value = scanned;
    unitError.value = '';
    form.unit_code = scanned.code;
    form.clearErrors('unit_code');
    scanning.value = false;
};

const submit = () => form.post('/sales/loans', { preserveScroll: true });
</script>

<template>
    <Head title="Peminjaman unit" />
    <SalesLayout>
        <Link href="/sales" class="back-link"><NavIcon name="back" :size="18" />Beranda</Link>
        <h1 class="page-title mt-1">Peminjaman unit</h1>
        <p class="mt-1 mb-7 text-nikkou-muted">Nama sales tercatat otomatis: {{ salesName }}.</p>

        <form novalidate @submit.prevent="submit">
            <h2 class="section-title"><span class="step">1</span>Jenis peminjaman</h2>
            <div class="segmented mt-3" role="radiogroup" aria-label="Jenis peminjaman">
                <button
                    v-for="(label, value) in types"
                    :key="value"
                    type="button"
                    role="radio"
                    :aria-checked="form.type === value"
                    @click="form.type = value"
                >
                    {{ label }}
                </button>
            </div>
            <p v-if="form.errors.type" class="field-error" role="alert">{{ form.errors.type }}</p>

            <h2 class="section-title"><span class="step">2</span>Unit</h2>
            <label for="unit_code" class="field-label">Kode unit</label>
            <div class="flex gap-2">
                <input
                    id="unit_code"
                    v-model="form.unit_code"
                    class="field-input flex-1"
                    placeholder="NK-0000"
                    autocomplete="off"
                    autocapitalize="characters"
                    @change="checkUnit"
                    @keydown.enter.prevent="checkUnit"
                />
                <button type="button" class="btn-outline min-h-[50px] w-auto flex-none px-5" @click="scanning = true"><NavIcon name="scan" :size="20" />Scan</button>
            </div>
            <p class="mt-2 text-xs text-nikkou-muted">Foto barcode, ketik kode, atau pakai alat scan USB/Bluetooth.</p>
            <p v-if="unitError || form.errors.unit_code" class="field-error" role="alert">{{ unitError || form.errors.unit_code }}</p>

            <div v-if="blocked" class="alert mt-3" role="alert">
                <div class="font-semibold">
                    {{ unit.status === 'on_loan' ? 'Unit ini masih tercatat dipinjam' : 'Unit ini belum bisa dipinjamkan' }}
                </div>
                <div class="text-[13px]">{{ unit.warning }}</div>
                <div v-if="!canOverride" class="mt-1 text-[13px]">Peminjaman tidak bisa disimpan. Hubungi admin bila perlu.</div>
            </div>

            <div v-if="blocked && canOverride">
                <label for="override_reason" class="field-label">Alasan override (khusus admin, tercatat di log)</label>
                <textarea id="override_reason" v-model="form.override_reason" rows="2" class="field-input"></textarea>
                <p v-if="form.errors.override_reason" class="field-error" role="alert">{{ form.errors.override_reason }}</p>
            </div>

            <div v-if="unit" class="tile mt-3">
                <div class="font-medium">{{ unit.type }} | {{ unit.code }}</div>
                <div class="text-xs text-nikkou-muted">Kelengkapan: {{ unit.components.length ? unit.components.join(', ') : '-' }}</div>
            </div>

            <h2 class="section-title"><span class="step">3</span>Pelanggan</h2>
            <p v-if="isEvent" class="mt-2 text-xs text-nikkou-muted">Untuk event, data pelanggan boleh dikosongkan.</p>
            <label for="customer_name" class="field-label">Nama</label>
            <input id="customer_name" v-model="form.customer_name" class="field-input" placeholder="Nama lengkap" autocomplete="off" />
            <p v-if="form.errors.customer_name" class="field-error" role="alert">{{ form.errors.customer_name }}</p>

            <label for="whatsapp_number" class="field-label">Nomor WhatsApp</label>
            <input id="whatsapp_number" v-model="form.whatsapp_number" class="field-input" placeholder="08xx xxxx xxxx" inputmode="tel" autocomplete="off" />
            <p v-if="form.errors.whatsapp_number" class="field-error" role="alert">{{ form.errors.whatsapp_number }}</p>

            <label for="address" class="field-label">Alamat</label>
            <textarea id="address" v-model="form.address" rows="2" class="field-input" placeholder="Alamat pengantaran"></textarea>
            <p v-if="form.errors.address" class="field-error" role="alert">{{ form.errors.address }}</p>

            <div class="flex gap-3">
                <div class="flex-1">
                    <label for="city" class="field-label">Kota</label>
                    <input id="city" v-model="form.city" class="field-input" placeholder="Surabaya" />
                    <p v-if="form.errors.city" class="field-error" role="alert">{{ form.errors.city }}</p>
                </div>
                <div class="flex-1">
                    <label for="district" class="field-label">Domisili</label>
                    <input id="district" v-model="form.district" class="field-input" placeholder="Kecamatan" />
                    <p v-if="form.errors.district" class="field-error" role="alert">{{ form.errors.district }}</p>
                </div>
            </div>

            <h2 class="section-title"><span class="step">4</span>Masa pinjam</h2>
            <div class="flex gap-3">
                <div class="flex-1">
                    <label for="starts_on" class="field-label">Mulai</label>
                    <input id="starts_on" v-model="form.starts_on" type="date" :max="today" class="field-input" />
                </div>
                <div class="flex-1">
                    <label for="due_on" class="field-label">Kembali</label>
                    <input id="due_on" v-model="form.due_on" type="date" :min="today" class="field-input" />
                </div>
            </div>
            <p v-if="form.errors.starts_on" class="field-error" role="alert">{{ form.errors.starts_on }}</p>
            <p v-if="form.errors.due_on" class="field-error" role="alert">{{ form.errors.due_on }}</p>

            <template v-if="isEvent">
                <label for="event_name" class="field-label">Nama event</label>
                <input id="event_name" v-model="form.event_name" class="field-input" placeholder="Misal: Pameran Kesehatan Surabaya" />
                <p v-if="form.errors.event_name" class="field-error" role="alert">{{ form.errors.event_name }}</p>
            </template>

            <h2 class="section-title"><span class="step">5</span>Persetujuan</h2>
            <label class="check-row">
                <input v-model="form.consent_data" type="checkbox" />
                <span>Pelanggan setuju datanya dipakai untuk keperluan peminjaman dan layanan.</span>
            </label>
            <p v-if="form.errors.consent_data" class="field-error" role="alert">{{ form.errors.consent_data }}</p>
            <label class="check-row">
                <input v-model="form.consent_whatsapp" type="checkbox" />
                <span>Pelanggan bersedia dihubungi lewat WhatsApp untuk follow-up dan survei pengalaman.</span>
            </label>

            <button type="submit" class="btn-primary mt-8" :disabled="!canSubmit">Simpan peminjaman</button>
            <p class="mt-2 text-xs text-nikkou-muted">Ringkasan untuk WhatsApp tersedia setelah peminjaman disimpan.</p>
        </form>

        <UnitScanner v-if="scanning" @select="useScanned" @close="scanning = false" />
    </SalesLayout>
</template>
