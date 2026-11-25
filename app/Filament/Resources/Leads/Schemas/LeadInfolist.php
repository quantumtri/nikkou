<?php

namespace App\Filament\Resources\Leads\Schemas;

use App\Enums\LeadPath;
use App\Models\Lead;
use App\Support\GenderIcon;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/** "See more" page of a lead: profile, product list, symptom form status. Follow-ups and invoices are relation tabs. */
class LeadInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Calon pelanggan')->columns(3)->schema([
                TextEntry::make('code')->label('ID lead')->fontFamily('mono')->copyable(),
                TextEntry::make('customer.name')->label('Nama'),
                TextEntry::make('customer.gender')->label('Gender')->html()
                    ->formatStateUsing(fn ($state) => GenderIcon::html($state)),
                TextEntry::make('location')->label('Kota, provinsi')
                    ->state(fn (Lead $r) => collect([$r->customer->city, $r->customer->province])->filter()->implode(', ') ?: '-'),
                TextEntry::make('customer.age_range')->label('Range usia')->formatStateUsing(fn ($state) => $state?->value ?? '-'),
                TextEntry::make('customer.whatsapp_number')->label('WhatsApp')->placeholder('-'),
                TextEntry::make('sales.name')->label('Sales'),
            ]),

            Section::make('Produk')->columns(3)->schema([
                TextEntry::make('path')->label('Jalur')->badge()->formatStateUsing(fn ($state) => $state->label()),
                TextEntry::make('product')->label(fn (Lead $r) => $r->path === LeadPath::Direct ? 'Produk dibeli' : 'Mencoba produk')
                    ->state(fn (Lead $r) => $r->productName() ?? '-'),
                TextEntry::make('trial_start')->label('Mulai coba')
                    ->state(fn (Lead $r) => $r->trialStart()?->locale('id')->translatedFormat('j F Y') ?? '-')
                    ->visible(fn (Lead $r) => $r->path === LeadPath::Trial),
                TextEntry::make('accessory_list')->label(fn (Lead $r) => $r->path === LeadPath::Direct ? 'Aksesoris (masuk invoice)' : 'Aksesoris dibawa saat trial')
                    ->state(fn (Lead $r) => $r->accessories->map(fn ($p) => $p->name.' x'.$p->pivot->quantity)->all() ?: ['-'])
                    ->listWithLineBreaks()->columnSpanFull(),
            ]),

            Section::make('Form gejala awal')->columns(3)->schema([
                IconEntry::make('symptom_form_filled')->label('Sudah diisi?')->boolean(),
                TextEntry::make('symptomForm.code')->label('No. dokumen')->fontFamily('mono')->placeholder('-'),
                TextEntry::make('symptomForm.filled_on')->label('Tanggal form')->date('j F Y')->placeholder('-'),
                TextEntry::make('symptomForm.session_location')->label('Sesi / lokasi')->placeholder('-')
                    ->visible(fn (Lead $r) => $r->symptomForm !== null),
                TextEntry::make('symptomForm.unit.code')->label('Mesin (ID inventori)')->fontFamily('mono')->placeholder('-')
                    ->visible(fn (Lead $r) => $r->symptomForm !== null),
                TextEntry::make('symptomForm.machine_taken_on')->label('Mesin dibawa pulang')->date('j F Y')->placeholder('-')
                    ->visible(fn (Lead $r) => $r->symptomForm !== null),
                TextEntry::make('survey')->label('Kondisi kesehatan yang dicentang')->columnSpanFull()
                    ->state(fn (Lead $r) => $r->symptomForm ? ($r->symptomForm->summaryLines() ?: ['Tidak ada yang dicentang.']) : ['Form belum diisi. Pakai tombol "Isi form gejala" di atas.'])
                    ->listWithLineBreaks()->color(fn (Lead $r) => $r->symptomForm ? null : 'gray'),
                TextEntry::make('symptomForm.sales_notes')->label('Catatan sales')->columnSpanFull()->placeholder('-')
                    ->visible(fn (Lead $r) => $r->symptomForm !== null),
            ]),

            Section::make('Status')->columns(3)->schema([
                TextEntry::make('status')->label('Status lead')->badge()->formatStateUsing(fn ($state) => $state->label())->color(fn ($state) => $state->color()),
                TextEntry::make('cancel_reason')->label('Alasan batal')->formatStateUsing(fn ($state) => $state?->label())
                    ->visible(fn (Lead $r) => $r->cancel_reason !== null),
                TextEntry::make('followup_count')->label('Follow-up')->suffix(' kali'),
                TextEntry::make('overall_response')->label('Overall response (follow-up terakhir)')->badge()->placeholder('Belum ada follow-up')
                    ->formatStateUsing(fn ($state) => $state?->label())->color(fn ($state) => $state?->color() ?? 'gray'),
                TextEntry::make('notes')->label('Catatan')->placeholder('-')->columnSpanFull(),
            ]),
        ]);
    }
}
