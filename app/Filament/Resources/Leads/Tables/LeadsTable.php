<?php

namespace App\Filament\Resources\Leads\Tables;

use App\Enums\LeadPath;
use App\Enums\LeadStatus;
use App\Enums\OverallResponse;
use App\Models\Lead;
use App\Models\Product;
use App\Models\User;
use App\Support\GenderIcon;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class LeadsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->searchPlaceholder('Cari nama')
            ->columns([
                TextColumn::make('code')->label('ID')->searchable()->sortable()->fontFamily('mono')->placeholder('-'),
                TextColumn::make('customer.name')->label('Nama')->searchable()->sortable(),
                TextColumn::make('location')->label('Kota, provinsi')
                    ->state(fn (Lead $r) => collect([$r->customer->city, $r->customer->province])->filter()->implode(', ') ?: '-'),
                TextColumn::make('customer.gender')->label('Gender')->alignCenter()->html()->tooltip(fn (Lead $r) => $r->customer->gender?->label())
                    ->formatStateUsing(fn ($state) => GenderIcon::html($state)),
                TextColumn::make('path')->label('Jalur')->badge()->formatStateUsing(fn ($state) => $state->label())
                    ->color(fn ($state) => $state === LeadPath::Direct ? 'primary' : 'gray'),
                TextColumn::make('product')->label('Produk')->state(fn (Lead $r) => $r->productName() ?? '-')->wrap(),
                TextColumn::make('trial_start')->label('Mulai coba')
                    ->state(fn (Lead $r) => $r->trialStart()?->locale('id')->translatedFormat('j M Y') ?? '-'),
                TextColumn::make('customer.age_range')->label('Usia')->formatStateUsing(fn ($state) => $state?->value ?? '-'),
                IconColumn::make('symptom_form_filled')->label('Form gejala')->boolean()->alignCenter(),
                TextColumn::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn (LeadStatus $state) => $state->label())
                    ->color(fn (LeadStatus $state) => $state->color()),
                TextColumn::make('followup_count')->label('Follow-up')->suffix(' kali')->alignCenter()->sortable(),
                TextColumn::make('overall_response')->label('Response')->badge()
                    ->placeholder('-')
                    ->formatStateUsing(fn (?OverallResponse $state) => $state?->label())
                    ->color(fn (?OverallResponse $state) => $state?->color() ?? 'gray'),
                TextColumn::make('sales.name')->label('Sales')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('path')->label('Jalur')->options(LeadPath::options()),
                SelectFilter::make('status')->label('Status')->options(LeadStatus::options()),
                SelectFilter::make('overall_response')->label('Response')->options(OverallResponse::options()),
                SelectFilter::make('product_id')->label('Produk')->options(fn () => Product::orderBy('name')->pluck('name', 'id')),
                SelectFilter::make('sales_id')->label('Sales')->options(fn () => User::where('role', 'sales')->orderBy('name')->pluck('name', 'id')),
                SelectFilter::make('symptom_form_filled')->label('Form gejala')->options(['1' => 'Sudah', '0' => 'Belum']),
            ])
            ->recordActions([ViewAction::make()->label('Lihat detail')->icon('heroicon-o-eye')])
            ->recordUrl(null);
    }
}
