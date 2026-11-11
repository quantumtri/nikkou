<script setup>
import { Head, usePage, useForm } from '@inertiajs/vue3';

const page = usePage();
const form = useForm({ email: '', password: '', remember: false });

const submit = () => form.post('/login', { onFinish: () => form.reset('password') });
</script>

<template>
    <Head title="Masuk" />
    <div class="mx-auto min-h-screen w-full max-w-[480px] bg-white px-5 py-12">
        <img src="/images/logo-wordmark.png" alt="NIKKOU" width="96" height="24" class="block h-6 w-auto" />
        <h1 class="page-title mt-10">Masuk</h1>
        <p class="mt-1 text-nikkou-muted">Gunakan akun yang diberikan admin.</p>

        <div v-if="page.props.flash?.success" role="status" class="notice mt-5">{{ page.props.flash.success }}</div>

        <form class="mt-4" novalidate @submit.prevent="submit">
            <label for="email" class="field-label">Email</label>
            <input id="email" v-model="form.email" type="email" autocomplete="username" required class="field-input" />
            <p v-if="form.errors.email" class="field-error" role="alert">{{ form.errors.email }}</p>

            <label for="password" class="field-label">Kata sandi</label>
            <input id="password" v-model="form.password" type="password" autocomplete="current-password" required class="field-input" />
            <p v-if="form.errors.password" class="field-error" role="alert">{{ form.errors.password }}</p>

            <label class="check-row items-center">
                <input v-model="form.remember" type="checkbox" />
                <span>Ingat saya di perangkat ini</span>
            </label>

            <button type="submit" class="btn-primary mt-6" :disabled="form.processing">Masuk</button>
        </form>
    </div>
</template>
