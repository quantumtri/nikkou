<?php

namespace App\Filament\Resources\CustomerMessages\Pages;

use App\Filament\Resources\CustomerMessages\CustomerMessageResource;
use Filament\Resources\Pages\ListRecords;

class ListCustomerMessages extends ListRecords
{
    protected static string $resource = CustomerMessageResource::class;

    protected static ?string $title = 'Balasan pelanggan';

    protected ?string $subheading = 'Pesan follow-up yang terkirim dan balasan pelanggan. Keluhan serius ditandai merah dan harus segera ditindaklanjuti.';
}
