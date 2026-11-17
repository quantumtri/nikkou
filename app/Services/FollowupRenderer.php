<?php

namespace App\Services;

use App\Models\FollowupTemplate;
use App\Models\Loan;

class FollowupRenderer
{
    /** Placeholders usable in template wording. */
    public const PLACEHOLDERS = ['{nama}', '{unit}', '{tanggal_kembali}', '{sales}'];

    public function render(FollowupTemplate $template, Loan $loan): string
    {
        $loan->loadMissing(['customer', 'unit', 'sales']);

        return strtr($template->body, [
            '{nama}' => $loan->customer?->name ?? '',
            '{unit}' => $loan->unit->code,
            '{tanggal_kembali}' => $loan->due_on->locale('id')->translatedFormat('j F Y'),
            '{sales}' => $loan->sales->name,
        ]);
    }
}
