<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Draft wording and keywords so the admin pages are never empty. Everything customer-facing starts
 * switched off and unapproved: it must be reviewed (health professional for safety wording) and approved
 * by an admin before anything is sent. Existing rows are never overwritten.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $templates = [
            ['borrower', 'first_session', 'Pemakaian pertama (beberapa jam setelah diserahkan)', 'Halo {nama}, ini tim Nikkou. Bagaimana pemakaian pertama unit {unit}? Kalau ada kendala saat memakai alatnya, balas pesan ini ya.', true],
            ['borrower', 'daily', 'Cek harian selama masa pinjam', 'Halo {nama}, selamat pagi. Bagaimana pengalaman Anda memakai unit {unit} hari ini? Silakan balas dengan cerita singkat atau kendala yang dialami.', true],
            ['borrower', 'reminder_h1', 'Pengingat H-1 pengambilan', 'Halo {nama}, mengingatkan bahwa unit {unit} dijadwalkan diambil besok ({tanggal_kembali}). Mohon siapkan unit beserta kelengkapannya. Terima kasih.', true],
            ['borrower', 'survey', 'Survei setelah unit dikembalikan', 'Halo {nama}, terima kasih sudah mencoba unit {unit}. Boleh kami minta masukan Anda tentang pengalaman memakainya? Balas dengan cerita singkat saja.', true],
            ['borrower', 'offer', 'Tawaran pembelian', 'Halo {nama}, bila Anda tertarik memiliki unit sendiri, tim kami siap membantu dengan informasi pemesanan. Balas pesan ini atau ketik 7 untuk bicara dengan operator.', true],
            ['system', 'safety_reply', 'Balasan standar untuk keluhan serius (wajib ditinjau tenaga kesehatan)', 'Terima kasih sudah mengabari kami. Mohon hentikan pemakaian alat dan segera hubungi dokter. Tim kami akan menghubungi Anda.', true],
            ['system', 'stop_confirm', 'Konfirmasi permintaan berhenti', 'Baik, kami tidak akan mengirim pesan follow-up lagi. Terima kasih.', false],
        ];

        foreach ($templates as [$group, $step, $title, $body, $review]) {
            DB::table('followup_templates')->insertOrIgnore([
                'group' => $group,
                'step_key' => $step,
                'title' => $title,
                'body' => $body,
                'requires_review' => $review,
                // The stop confirmation is a plain acknowledgement; everything else starts off.
                'is_active' => ! $review,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $keywords = ['sesak', 'nyeri dada', 'sakit dada', 'dada sakit', 'dada terasa', 'tidak nyaman', 'pingsan', 'kejang', 'pusing',
            'sakit kepala', 'berdebar', 'mual', 'muntah', 'lemas', 'alergi', 'ruam', 'gatal', 'bengkak', 'berdarah'];

        foreach ($keywords as $keyword) {
            DB::table('safety_keywords')->insertOrIgnore(['keyword' => $keyword, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        // Left in place: the admin may have edited or approved them.
    }
};
