<?php

namespace App\Filament\Resources\FollowupTemplates\Pages;

use App\Filament\Resources\FollowupTemplates\FollowupTemplateResource;
use Filament\Resources\Pages\ListRecords;

class ListFollowupTemplates extends ListRecords
{
    protected static string $resource = FollowupTemplateResource::class;

    protected static ?string $title = 'Teks follow-up';

    protected ?string $subheading = 'Semua teks mulai dalam keadaan nonaktif. Teks baru dikirim ke pelanggan setelah ditinjau dan disetujui di sini. Jadwal pengiriman diatur sistem: 10.00 WIB.';
}
