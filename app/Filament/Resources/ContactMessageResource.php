<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use PtPlugins\FilamentCollapsibleColumnGroup\CollapsibleColumnGroup;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Actions\ViewAction;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\ContactMessageResource\Pages\ListContactMessages;
use App\Filament\Resources\ContactMessageResource\Pages\ViewContactMessage;
use App\Filament\Resources\ContactMessageResource\Pages;
use App\Models\ContactMessage;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;


class ContactMessageResource extends Resource
{
    protected static ?string $model = ContactMessage::class;
    protected static string | \BackedEnum | null $navigationIcon = 'phosphor-envelope';
    protected static ?string $navigationLabel = 'Contact & Reports';
    protected static string | \UnitEnum | null $navigationGroup = 'Users & Messages';
    protected static ?int $navigationSort = 2;

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('is_read', false)->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Message Details')
                    ->schema([
                        TextInput::make('type')
                            ->disabled()
                            ->formatStateUsing(fn ($state) => ucfirst($state ?? 'contact')),
                        TextInput::make('name')
                            ->label(fn ($record) => $record?->type === 'report' ? 'Reporter' : 'Name')
                            ->disabled(),
                        TextInput::make('email')
                            ->disabled(),
                        TextInput::make('subject')
                            ->disabled()
                            ->columnSpanFull(),
                        Textarea::make('message')
                            ->disabled()
                            ->rows(8)
                            ->columnSpanFull(),
                        Toggle::make('is_read')
                            ->label('Mark as Read'),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                CollapsibleColumnGroup::make('Status')
                    ->collapsible()
                    ->columns([
                        IconColumn::make('is_read')
                            ->boolean()
                            ->label('')
                            ->trueIcon('phosphor-envelope-open')
                            ->falseIcon('phosphor-envelope')
                            ->trueColor('gray')
                            ->falseColor('danger')
                            ->grow(false),
                        TextColumn::make('type')
                            ->badge()
                            ->formatStateUsing(fn ($state) => ucfirst($state ?? 'contact'))
                            ->color(fn ($state) => match ($state) {
                                'report' => 'danger',
                                default => 'info',
                            })
                            ->grow(false),
                    ]),

                CollapsibleColumnGroup::make('Message')
                    ->collapsible()
                    ->columns([
                        TextColumn::make('name')
                            ->label('From')
                            ->searchable()
                            ->sortable()
                            ->weight(fn (ContactMessage $record) => $record->is_read ? 'normal' : 'bold'),
                        TextColumn::make('email')
                            ->searchable()
                            ->sortable()
                            ->icon('phosphor-envelope')
                            ->size('sm')
                            ->copyable()
                            ->toggleable(isToggledHiddenByDefault: true),
                        TextColumn::make('subject')
                            ->searchable()
                            ->limit(50)
                            ->placeholder('(no subject)')
                            ->weight(fn (ContactMessage $record) => $record->is_read ? 'normal' : 'bold'),
                        TextColumn::make('message')
                            ->limit(60)
                            ->size('sm')
                            ->color('gray')
                            ->toggleable(isToggledHiddenByDefault: true),
                    ]),

                CollapsibleColumnGroup::make('Dates')
                    ->collapsible()
                    ->columns([
                        TextColumn::make('created_at')
                            ->label('Received')
                            ->since()
                            ->sortable()
                            ->size('sm')
                            ->color('gray')
                            ->tooltip(fn (ContactMessage $record): string => $record->created_at?->format('M j, Y g:i A') ?? ''),
                    ]),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        'contact' => 'Contact',
                        'report' => 'Report',
                    ])
                    ->label('Type'),

                TernaryFilter::make('is_read')
                    ->label('Read Status')
                    ->trueLabel('Read')
                    ->falseLabel('Unread'),
            ])
            ->recordActions([
                ViewAction::make(),

                Action::make('toggle_read')
                    ->icon(fn (ContactMessage $record) => $record->is_read ? 'phosphor-envelope' : 'phosphor-envelope-open')
                    ->label(fn (ContactMessage $record) => $record->is_read ? 'Mark Unread' : 'Mark Read')
                    ->action(fn (ContactMessage $record) => $record->update(['is_read' => !$record->is_read])),

                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('mark_read')
                        ->icon('phosphor-envelope-open')
                        ->action(fn (Collection $records) => $records->each(fn ($r) => $r->update(['is_read' => true])))
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('mark_unread')
                        ->icon('phosphor-envelope')
                        ->action(fn (Collection $records) => $records->each(fn ($r) => $r->update(['is_read' => false])))
                        ->deselectRecordsAfterCompletion(),

                    DeleteBulkAction::make(),
                ]),
            ])
            ->striped();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContactMessages::route('/'),
            'view' => ViewContactMessage::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
