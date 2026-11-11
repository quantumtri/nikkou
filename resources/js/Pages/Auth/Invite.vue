<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({
    name: String,
    email: String,
    isInvite: Boolean,
});

const page = usePage();
const form = useForm({ password: '', password_confirmation: '' });

// The page URL carries the signed query string, so posting to it keeps the link valid.
const submit = () => form.post(page.url, { onFinish: () => form.reset() });
</script>

<template>
    <Head :title="isInvite ? 'Aktifkan akun' : 'Atur ulang kata sandi'" />
    <div class="mx-auto min-h-screen w-full max-w-[480px] bg-white px-5 py-12">
        <img src="/images/logo-wordmark.png" alt="NIKKOU" width="96" height="24" class="block h-6 w-auto" />
        <h1 class="page-title mt-10">{{ isInvite ? 'Aktifkan akun Anda' : 'Atur ulang kata sandi' }}</h1>
        <p class="mt-1 text-nikkou-muted">Halo, {{ name }}. Pilih kata sandi untuk masuk sebagai <strong class="font-semibold">{{ email }}</strong>.</p>

        <form class="mt-4" novalidate @submit.prevent="submit">
            <label for="password" class="field-label">Kata sandi baru</label>
            <input id="password" v-model="form.password" type="password" autocomplete="new-password" required class="field-input" />
            <p class="mt-1.5 text-xs text-nikkou-muted">Minimal 10 karakter, mengandung huruf dan angka.</p>
            <p v-if="form.errors.password" class="field-error" role="alert">{{ form.errors.password }}</p>

            <label for="password_confirmation" class="field-label">Ulangi kata sandi</label>
            <input id="password_confirmation" v-model="form.password_confirmation" type="password" autocomplete="new-password" required class="field-input" />

            <button type="submit" class="btn-primary mt-6" :disabled="form.processing">Simpan kata sandi</button>
        </form>
    </div>
</template>
