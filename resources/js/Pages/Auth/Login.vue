<script setup>
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({ email: '', password: '', remember: false });

const submit = () => form.post('/login', { onFinish: () => form.reset('password') });
</script>

<template>
    <Head title="Masuk" />
    <div class="mx-auto min-h-screen w-full max-w-[480px] bg-white px-5 py-10">
        <img src="/images/logo-wordmark.png" alt="NIKKOU" class="block h-6 w-auto" />
        <h1 class="mt-8 text-[28px] leading-snug text-nikkou-red-deep">Masuk</h1>
        <p class="text-nikkou-muted">Gunakan akun yang diberikan admin.</p>

        <form class="mt-4" novalidate @submit.prevent="submit">
            <label for="email" class="field-label">Email</label>
            <input id="email" v-model="form.email" type="email" autocomplete="username" required class="field-input" />
            <p v-if="form.errors.email" class="field-error" role="alert">{{ form.errors.email }}</p>

            <label for="password" class="field-label">Kata sandi</label>
            <input id="password" v-model="form.password" type="password" autocomplete="current-password" required class="field-input" />
            <p v-if="form.errors.password" class="field-error" role="alert">{{ form.errors.password }}</p>

            <label class="mt-4 flex min-h-[44px] items-center gap-2.5">
                <input v-model="form.remember" type="checkbox" class="h-[22px] w-[22px]" />
                <span>Ingat saya di perangkat ini</span>
            </label>

            <button type="submit" class="btn-primary mt-6" :disabled="form.processing">Masuk</button>
        </form>
    </div>
</template>
