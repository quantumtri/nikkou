<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import NavIcon from '@/Components/NavIcon.vue';
import UnitScanner from '@/Components/UnitScanner.vue';
import SalesLayout from '@/Layouts/SalesLayout.vue';
import { lookupUnit, statusChipClass } from '@/lib/units';

const props = defineProps({
    isWarehouse: Boolean,
    prefillCode: { type: String, default: '' },
});

const form = useForm({
    unit_code: props.prefillCode,
    checks: [],
    photos: [],
    has_damage: false,
    notes: '',
    status: 'available',
});

const unit = ref(null);
const unitError = ref('');
const scanning = ref(false);
const previews = ref([]);

const loan = computed(() => unit.value?.active_loan ?? null);
const canReturn = computed(() => Boolean(loan.value?.can_return));
const answered = computed(() => form.checks.length > 0 && form.checks.every((check) => check.is_complete !== null));
const missing = computed(() => form.checks.filter((check) => check.is_complete === false).map((check) => check.component));
// Missing parts or damage always send the unit to service, whatever is chosen below.
const forcedService = computed(() => missing.value.length > 0 || form.has_damage);
const finalStatus = computed(() => (forcedService.value ? 'needs_service' : form.status));
const canSubmit = computed(
    () => canReturn.value && answered.value && form.photos.length > 0 && (!form.has_damage || form.notes.trim() !== '') && !form.processing,
);

const applyUnit = (found) => {
    unit.value = found;
    unitError.value = '';
    form.unit_code = found.code;
    form.clearErrors();
    form.checks = (found.component_names ?? []).map((component) => ({ component, is_complete: null }));
};

const checkUnit = async () => {
    unitError.value = '';
    form.clearErrors('unit_code');

    if (!form.unit_code.trim()) {
        unit.value = null;
        form.checks = [];
        return;
    }

    try {
        applyUnit(await lookupUnit(form.unit_code));
    } catch (e) {
        unit.value = null;
        form.checks = [];
        unitError.value = e.message;
    }
};

const useScanned = (scanned) => {
    applyUnit(scanned);
    scanning.value = false;
};

const addPhotos = (event) => {
    const files = Array.from(event.target.files ?? []);
    event.target.value = '';

    for (const file of files.slice(0, 6 - form.photos.length)) {
        form.photos.push(file);
        previews.value.push(URL.createObjectURL(file));
    }
};

const removePhoto = (index) => {
    URL.revokeObjectURL(previews.value[index]);
    previews.value.splice(index, 1);
    form.photos.splice(index, 1);
};

const submit = () => {
    form.status = finalStatus.value;
    form.post('/sales/returns', {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            previews.value.forEach((url) => URL.revokeObjectURL(url));
            previews.value = [];
            unit.value = null;
            form.reset();
        },
    });
};

onMounted(() => {
    if (props.prefillCode) checkUnit();
});
onBeforeUnmount(() => previews.value.forEach((url) => URL.revokeObjectURL(url)));
</script>

<template>
    <Head title="Pengembalian unit" />
    <SalesLayout>
        <a v-if="isWarehouse" href="/admin" class="back-link"><NavIcon name="back" :size="18" />Kembali ke panel</a>
        <Link v-else href="/sales" class="back-link"><NavIcon name="back" :size="18" />Beranda</Link>
        <h1 class="page-title mt-1">Pengembalian unit</h1>
        <p class="mt-1 mb-7 text-nikkou-muted">Scan unit yang masuk, cek kelengkapan, lalu foto kondisinya.</p>

        <form novalidate @submit.prevent="submit">
            <h2 class="section-title"><span class="step">1</span>Scan unit masuk</h2>
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
                <button type="button" class="btn-outline min-h-[50px] w-auto flex-none px-5" @click="scanning = true">
                    <NavIcon name="scan" :size="20" />Scan
                </button>
            </div>
            <p v-if="unitError || form.errors.unit_code" class="field-error" role="alert">{{ unitError || form.errors.unit_code }}</p>

            <div v-if="unit && !loan" class="alert mt-3" role="alert">
                <div class="font-semibold">Unit ini tidak sedang dipinjam</div>
                <div class="text-[13px]">{{ unit.code }} berstatus {{ unit.status_label }}, jadi tidak ada pengembalian yang perlu dicatat.</div>
            </div>
            <div v-else-if="unit && !canReturn" class="alert mt-3" role="alert">
                <div class="font-semibold">Unit ini dipinjam sales lain</div>
                <div class="text-[13px]">Dipinjam {{ loan.borrower }} atas nama {{ loan.sales }}. Hanya peminjam, admin, atau staf gudang yang bisa mencatat pengembaliannya.</div>
            </div>
            <div v-else-if="unit" class="tile mt-3">
                <div class="font-medium">{{ unit.type }} | {{ unit.code }}</div>
                <div class="mt-0.5 text-xs text-nikkou-muted">Dipinjam {{ loan.borrower }} | {{ loan.type_label }} | {{ loan.starts_on }} sampai {{ loan.due_on }}</div>
                <span class="chip chip-bad mt-2">{{ unit.status_label }}</span>
            </div>

            <template v-if="canReturn">
                <h2 class="section-title"><span class="step">2</span>Cek kelengkapan</h2>
                <fieldset v-for="(check, index) in form.checks" :key="check.component" class="border-b border-nikkou-line py-3">
                    <legend class="sr-only">{{ check.component }}</legend>
                    <div class="font-medium" aria-hidden="true">{{ check.component }}</div>
                    <div class="segmented mt-2" role="radiogroup" :aria-label="check.component">
                        <button type="button" role="radio" :aria-checked="check.is_complete === true" @click="form.checks[index].is_complete = true">Ada</button>
                        <button type="button" role="radio" :aria-checked="check.is_complete === false" @click="form.checks[index].is_complete = false">Tidak ada</button>
                    </div>
                </fieldset>
                <p v-if="!answered" class="mt-2 text-xs text-nikkou-muted">Pilih Ada atau Tidak ada untuk setiap komponen.</p>
                <p v-if="form.errors.checks" class="field-error" role="alert">{{ form.errors.checks }}</p>

                <h2 class="section-title"><span class="step">3</span>Foto kondisi</h2>
                <p class="mt-2 text-xs text-nikkou-muted">Minimal 1 foto, maksimal 6.</p>
                <div class="mt-3 grid grid-cols-3 gap-2">
                    <div v-for="(src, index) in previews" :key="src" class="relative aspect-square overflow-hidden rounded-[14px] bg-nikkou-mist">
                        <img :src="src" :alt="`Foto kondisi ${index + 1}`" class="h-full w-full object-cover" />
                        <button
                            type="button"
                            class="absolute top-1 right-1 flex h-11 w-11 items-center justify-center rounded-full bg-white text-lg text-nikkou-red-deep"
                            :aria-label="`Hapus foto ${index + 1}`"
                            @click="removePhoto(index)"
                        >
                            &times;
                        </button>
                    </div>
                    <label
                        v-if="form.photos.length < 6"
                        class="flex aspect-square cursor-pointer items-center justify-center rounded-[14px] border-[1.5px] border-nikkou-red-deep text-center text-sm font-medium text-nikkou-red-deep focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-nikkou-red-deep"
                    >
                        Tambah foto
                        <input type="file" accept="image/*" capture="environment" multiple class="sr-only" @change="addPhotos" />
                    </label>
                </div>
                <p v-if="form.errors.photos" class="field-error" role="alert">{{ form.errors.photos }}</p>
                <p v-for="(message, key) in form.errors" v-show="String(key).startsWith('photos.')" :key="key" class="field-error" role="alert">{{ message }}</p>

                <label class="check-row">
                    <input v-model="form.has_damage" type="checkbox" />
                    <span>Ada kerusakan pada unit atau kelengkapannya</span>
                </label>
                <label for="notes" class="field-label">Catatan kondisi atau kerusakan{{ form.has_damage ? ' (wajib)' : '' }}</label>
                <textarea id="notes" v-model="form.notes" rows="3" class="field-input" placeholder="Misal: ada goresan di sisi kiri"></textarea>
                <p v-if="form.errors.notes" class="field-error" role="alert">{{ form.errors.notes }}</p>

                <h2 class="section-title"><span class="step">4</span>Status setelah kembali</h2>
                <div class="segmented mt-3" role="radiogroup" aria-label="Status setelah kembali">
                    <button type="button" role="radio" :aria-checked="finalStatus === 'available'" :disabled="forcedService" @click="form.status = 'available'">Tersedia</button>
                    <button type="button" role="radio" :aria-checked="finalStatus === 'needs_service'" @click="form.status = 'needs_service'">Perlu servis</button>
                </div>
                <p v-if="forcedService" class="mt-2 text-[13px] text-nikkou-red-ink" role="status">
                    {{ missing.length ? `Kelengkapan kurang (${missing.join(', ')}).` : 'Ada kerusakan.' }}
                    Unit otomatis ditandai Perlu servis dan admin diberi tahu.
                </p>
                <p v-else class="mt-2 text-xs text-nikkou-muted">Bila kelengkapan tidak lengkap atau ada kerusakan, unit otomatis ditandai Perlu servis.</p>

                <button type="submit" class="btn-primary mt-8" :disabled="!canSubmit">Simpan pengembalian</button>
            </template>
        </form>

        <UnitScanner v-if="scanning" @select="useScanned" @close="scanning = false" />
    </SalesLayout>
</template>
