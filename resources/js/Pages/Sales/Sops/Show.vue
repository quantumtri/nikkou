<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import NavIcon from '@/Components/NavIcon.vue';
import SalesLayout from '@/Layouts/SalesLayout.vue';

const props = defineProps({
    sop: Object,
    version: Object,
    steps: Array,
    forms: Array,
    svg: String,
    is_read: Boolean,
    can_acknowledge: Boolean,
});

const selected = ref(null);
const busy = ref(false);

function pick(event) {
    const node = event.target.closest('[data-step]');
    if (node) selected.value = props.steps.find((s) => s.key === node.dataset.step) ?? null;
}

function acknowledge() {
    busy.value = true;
    router.post(`/sales/sops/${props.sop.id}/acknowledge`, {}, { preserveScroll: true, onFinish: () => (busy.value = false) });
}
</script>

<template>
    <Head :title="sop.code" />
    <SalesLayout>
        <Link href="/sales/sops" class="back-link"><NavIcon name="back" :size="18" />Daftar SOP</Link>
        <h1 class="page-title mt-1">{{ sop.title }}</h1>
        <p class="mt-1 text-sm text-nikkou-muted">{{ sop.code }} | Versi {{ version.number }}<template v-if="version.effective_from"> | Berlaku {{ version.effective_from }}</template></p>

        <div v-if="can_acknowledge" class="mt-4">
            <span v-if="is_read" class="chip chip-neutral">Anda sudah membaca versi {{ version.number }}</span>
            <button v-else type="button" class="btn-primary w-full" :disabled="busy" @click="acknowledge">Saya sudah membaca versi {{ version.number }}</button>
        </div>

        <h2 class="section-title">Langkah</h2>
        <ol class="mt-3 flex flex-col gap-3">
            <li v-for="step in steps" :key="step.key" class="card p-4">
                <div class="flex items-start gap-3">
                    <span v-if="step.number" class="mt-0.5 flex h-6 w-6 flex-none items-center justify-center rounded-full bg-nikkou-red-deep text-xs font-semibold text-white">{{ step.number }}</span>
                    <div class="min-w-0">
                        <div class="font-medium">{{ step.title }}</div>
                        <div class="text-xs text-nikkou-muted">{{ step.role || '-' }}<template v-if="step.deadline"> | {{ step.deadline }}</template></div>
                        <p v-if="step.description" class="mt-2 text-sm">{{ step.description }}</p>
                        <ul v-if="step.next.length" class="mt-2 text-xs text-nikkou-muted">
                            <li v-for="n in step.next" :key="n.to + n.label">{{ n.label ? n.label + ': ' : 'Lanjut: ' }}{{ n.to }}</li>
                        </ul>
                    </div>
                </div>
            </li>
        </ol>

        <h2 class="section-title">Diagram alur</h2>
        <div class="card mt-3 overflow-x-auto p-2" @click="pick" @keydown.enter="pick" v-html="svg" />
        <div v-if="selected" class="card mt-3 p-4" aria-live="polite">
            <div class="text-xs text-nikkou-muted">{{ selected.type_label }}<template v-if="selected.number"> {{ selected.number }}</template></div>
            <div class="font-medium">{{ selected.title }}</div>
            <div class="text-sm text-nikkou-muted">{{ selected.role }}</div>
            <p v-if="selected.description" class="mt-2 text-sm">{{ selected.description }}</p>
        </div>

        <template v-if="forms.length">
            <h2 class="section-title">Formulir terkait</h2>
            <ul class="card mt-3 divide-y divide-nikkou-line py-1">
                <li v-for="f in forms" :key="f.name" class="py-2.5 text-sm">{{ f.name }}</li>
            </ul>
        </template>
    </SalesLayout>
</template>
