<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import NavIcon from '@/Components/NavIcon.vue';
import { cameraSupported, decodeFile, startCamera } from '@/lib/barcode';
import { lookupUnit, statusChipClass } from '@/lib/units';

const emit = defineEmits(['select', 'close']);

const tabs = [
    { id: 'camera', label: 'Foto barcode' },
    { id: 'type', label: 'Ketik kode' },
    { id: 'scanner', label: 'Alat scan' },
];

const tab = ref('camera');
const code = ref('');
const result = ref(null);
const error = ref('');
const cameraError = ref('');
const busy = ref(false);
const video = ref(null);
const codeInput = ref(null);

let stopCamera = null;
let lastScanned = '';

const find = async (value) => {
    error.value = '';
    busy.value = true;

    try {
        result.value = await lookupUnit(value);
        code.value = result.value.code;
    } catch (e) {
        result.value = null;
        error.value = e.message;
    } finally {
        busy.value = false;
    }
};

const onScanned = (value) => {
    // The camera reports the same code many times per second.
    if (!value || value === lastScanned) return;
    lastScanned = value;
    find(value);
};

const openCamera = async () => {
    cameraError.value = '';

    if (!cameraSupported()) {
        cameraError.value = 'Kamera langsung tidak tersedia di perangkat ini. Pakai "Ambil foto" atau ketik kode.';
        return;
    }

    try {
        stopCamera = await startCamera(video.value, onScanned);
    } catch {
        cameraError.value = 'Kamera tidak bisa dibuka. Izinkan akses kamera, atau pakai "Ambil foto".';
    }
};

const closeCamera = () => {
    stopCamera?.();
    stopCamera = null;
};

const onFile = async (event) => {
    const file = event.target.files?.[0];
    event.target.value = '';
    if (!file) return;

    error.value = '';
    busy.value = true;

    try {
        const value = await decodeFile(file);

        if (!value) {
            error.value = 'Kode tidak terbaca di foto ini. Coba foto lebih dekat dan terang, atau ketik kodenya.';
            return;
        }

        lastScanned = '';
        await find(value);
    } catch {
        error.value = 'Foto tidak bisa dibaca. Coba lagi atau ketik kodenya.';
    } finally {
        busy.value = false;
    }
};

watch(tab, async (value) => {
    if (value === 'camera') {
        await nextTick();
        openCamera();
    } else {
        closeCamera();
        await nextTick();
        codeInput.value?.focus();
    }
});

onMounted(openCamera);
onBeforeUnmount(closeCamera);
</script>

<template>
    <div class="fixed inset-0 z-50 overflow-y-auto bg-white" role="dialog" aria-modal="true" aria-labelledby="scanner-title">
        <div class="mx-auto w-full max-w-[480px] px-5 py-5">
            <button type="button" class="back-link" @click="emit('close')"><NavIcon name="back" :size="18" />Kembali ke form</button>
            <h1 id="scanner-title" class="page-title mt-1 mb-4">Scan unit</h1>

            <div class="segmented" role="tablist">
                <button
                    v-for="item in tabs"
                    :key="item.id"
                    type="button"
                    role="tab"
                    :aria-selected="tab === item.id"
                    class="!text-sm"
                    @click="tab = item.id"
                >
                    {{ item.label }}
                </button>
            </div>

            <div v-if="tab === 'camera'">
                <div class="relative mt-4 h-[300px] overflow-hidden rounded-[28px] bg-nikkou-ink">
                    <video ref="video" class="h-full w-full object-cover" muted playsinline aria-label="Tampilan kamera"></video>
                    <div class="pointer-events-none absolute inset-10 rounded-[20px] border-[3px] border-white" aria-hidden="true"></div>
                    <div class="absolute inset-x-0 bottom-0 bg-nikkou-ink px-4 py-2.5 text-center text-[13px] text-white">
                        {{ cameraError || 'Arahkan kamera ke label QR / barcode' }}
                    </div>
                </div>

                <div class="mt-3 flex gap-3">
                    <label class="btn-outline min-h-[48px] cursor-pointer px-3 text-sm focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-nikkou-red-deep">
                        Ambil foto
                        <input type="file" accept="image/*" capture="environment" class="sr-only" @change="onFile" />
                    </label>
                    <label class="btn-outline min-h-[48px] cursor-pointer px-3 text-sm focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-nikkou-red-deep">
                        Pilih dari galeri
                        <input type="file" accept="image/*" class="sr-only" @change="onFile" />
                    </label>
                </div>
            </div>

            <form v-else class="mt-4" @submit.prevent="find(code)">
                <label for="scan-code" class="field-label mt-0">
                    {{ tab === 'scanner' ? 'Kode dari alat scan' : 'Kode unit' }}
                </label>
                <div class="flex gap-2">
                    <input
                        id="scan-code"
                        ref="codeInput"
                        v-model="code"
                        class="field-input flex-1"
                        placeholder="NK-0000"
                        autocomplete="off"
                        autocapitalize="characters"
                        enterkeyhint="search"
                    />
                    <button type="submit" class="btn-outline min-h-[50px] w-auto flex-none px-6" :disabled="busy">Cari</button>
                </div>
                <p class="mt-2 text-xs text-nikkou-muted">
                    {{
                        tab === 'scanner'
                            ? 'Alat scan USB atau Bluetooth: pastikan kursor di kolom ini lalu scan. Kode terisi dan dicari otomatis.'
                            : 'Ketik kode yang tertera di label, lalu tekan Cari.'
                    }}
                </p>
            </form>

            <p v-if="busy" class="mt-4 text-nikkou-muted" role="status">Memeriksa unit...</p>
            <p v-if="error" class="alert mt-4" role="alert">{{ error }}</p>

            <div v-if="result" class="card mt-5" aria-live="polite">
                <div class="text-xs text-nikkou-muted">Hasil scan</div>
                <div class="text-lg font-semibold">{{ result.code }} | {{ result.type }}</div>
                <div class="mt-1 flex items-center gap-2">
                    <span class="chip" :class="statusChipClass(result.status)">{{ result.status_label }}</span>
                    <span class="text-xs text-nikkou-muted">{{ result.location }}</span>
                </div>
                <p v-if="result.warning" class="mt-2 text-[13px] text-nikkou-red-ink">{{ result.warning }}</p>
            </div>

            <button v-if="result" type="button" class="btn-primary mt-4" @click="emit('select', result)">Pakai unit ini</button>
        </div>
    </div>
</template>
