<?php

namespace App\Filament\Resources\Leads\RelationManagers;

use App\Enums\FollowUpChannel;
use App\Enums\OverallResponse;
use App\Models\Lead;
use App\Services\FollowUpService;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Follow-up history of a lead. Every row added is one follow-up; its response feeds the lead's overall response. */
class FollowUpsRelationManager extends RelationManager
{
    protected static string $relationship = 'followUps';

    protected static ?string $title = 'Follow-up';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            DatePicker::make('followed_up_on')->label('Tanggal follow-up')->default(today())->maxDate(today())->required(),
            Select::make('channel')->label('Lewat')->options(FollowUpChannel::options())->default(FollowUpChannel::WhatsApp->value)->required()->selectablePlaceholder(false),
            Select::make('response')->label('Response pelanggan')->options(OverallResponse::options())->required(),
            Textarea::make('notes')->label('Catatan pemakaian')->rows(3)->maxLength(2000),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('followed_up_on')
            ->heading(fn () => 'Follow-up ('.$this->getOwnerRecord()->followUps()->count().' kali)')
            ->columns([
                TextColumn::make('followed_up_on')->label('Tanggal')->date('j M Y'),
                TextColumn::make('channel')->label('Lewat')->formatStateUsing(fn ($state) => $state->label()),
                TextColumn::make('response')->label('Response')->badge()->formatStateUsing(fn ($state) => $state->label())->color(fn ($state) => $state->color()),
                TextColumn::make('notes')->label('Catatan')->wrap()->placeholder('-'),
                TextColumn::make('user.name')->label('Oleh'),
            ])
            ->headerActions([
                CreateAction::make()->label('Tambah follow-up')->modalHeading('Tambah follow-up')
                    ->using(function (array $data, RelationManager $livewire) {
                        /** @var Lead $lead */
                        $lead = $livewire->getOwnerRecord();
                        $service = app(FollowUpService::class);

                        return $service->add($lead, $service->validate($data), auth()->user());
                    })
                    ->after(fn (RelationManager $livewire) => $livewire->getOwnerRecord()->refresh())
                    ->successNotificationTitle('Follow-up ditambahkan'),
            ])
            ->paginated(false)
            ->emptyStateHeading('Belum ada follow-up')
            ->emptyStateDescription('Setiap follow-up yang ditambahkan menambah hitungan, dan response terakhir menjadi overall response.');
    }
}
