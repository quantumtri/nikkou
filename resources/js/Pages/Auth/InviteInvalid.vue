<script setup>
import { Head } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Tautan tidak berlaku" />
    <div class="mx-auto min-h-screen w-full max-w-[480px] bg-white px-5 py-12">
        <img src="/images/logo-wordmark.png" alt="NIKKOU" width="96" height="24" class="block h-6 w-auto" />
        <h1 class="page-title mt-10">Tautan tidak berlaku</h1>
        <p class="mt-2 text-nikkou-muted">
            Tautan ini sudah dipakai, sudah diganti dengan yang lebih baru, atau sudah kedaluwarsa. Minta admin mengirim tautan baru.
        </p>
        <a href="/login" class="btn-outline mt-6">Ke halaman masuk</a>
    </div>
</template>
