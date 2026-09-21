<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\RequiresSuperAdmin;
use App\Filament\Resources\EncodeProfileResource\Pages\CreateEncodeProfile;
use App\Filament\Resources\EncodeProfileResource\Pages\EditEncodeProfile;
use App\Filament\Resources\EncodeProfileResource\Pages\ListEncodeProfiles;
use App\Models\EncodeProfile;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

/**
 * The resolution ladder videos are transcoded to.
 *
 * Super-admin only, like the other settings that shape ffmpeg commands.
 */
class EncodeProfileResource extends Resource
{
    use RequiresSuperAdmin;

    protected static ?string $model = EncodeProfile::class;

    protected static string|\BackedEnum|null $navigationIcon = 'phosphor-film-strip';

    protected static ?string $navigationLabel = 'Encoding Profiles';

    protected static string|\UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->description('Videos are encoded to every active profile below their height. Changes apply to new uploads; use "Encode missing renditions" for existing videos.')
                    ->schema([
                        TextInput::make('name')
                            ->label('Quality Label')
                            ->helperText('Shown in the player\'s quality menu, e.g. 720p.')
                            ->required()
                            ->maxLength(32)
                            ->regex('/^[A-Za-z0-9_-]+$/')
                            ->unique(ignoreRecord: true)
                            ->notIn(['original']),
                        Select::make('codec')
                            ->options(EncodeProfile::CODECS)
                            ->default('h264')
                            ->required()
                            ->selectablePlaceholder(false)
                            ->helperText('H.264 plays in every browser, both as MP4 and over HLS.'),
                        TextInput::make('width')
                            ->numeric()
                            ->required()
                            ->minValue(64)
                            ->maxValue(7680)
                            ->helperText('Nominal 16:9 width, used in the HLS playlist. Output keeps the source aspect ratio.'),
                        TextInput::make('height')
                            ->numeric()
                            ->required()
                            ->minValue(64)
                            ->maxValue(4320)
                            ->helperText('Output height in pixels.'),
                        TextInput::make('video_bitrate')
                            ->label('Video Bitrate')
                            ->required()
                            ->maxLength(16)
                            ->regex('/^\d+(\.\d+)?[kKmM]?$/')
                            ->placeholder('2800k')
                            ->helperText('Used when rate control is "Bitrate", and as the HLS bandwidth hint.'),
                        Toggle::make('is_active')
                            ->label('Active')
                            ->inline(false),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('height')
            ->columns([
                TextColumn::make('name')
                    ->label('Quality')
                    ->weight('bold')
                    ->searchable(),
                TextColumn::make('resolution')
                    ->state(fn (EncodeProfile $record): string => "{$record->width}×{$record->height}")
                    ->toggleable(),
                TextColumn::make('video_bitrate')
                    ->label('Bitrate')
                    ->toggleable(),
                TextColumn::make('codec')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => EncodeProfile::CODECS[$state] ?? $state)
                    ->color('gray')
                    ->toggleable(),
                TextColumn::make('encodings_count')
                    ->label('Renditions')
                    ->counts('encodings')
                    ->sortable()
                    ->toggleable(),
                ToggleColumn::make('is_active')
                    ->label('Active')
                    ->toggleable(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->paginated(false)
            ->emptyStateIcon('phosphor-film-strip')
            ->emptyStateHeading('No encoding profiles')
            ->emptyStateDescription('Without an active profile, videos are published at their original resolution only.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEncodeProfiles::route('/'),
            'create' => CreateEncodeProfile::route('/create'),
            'edit' => EditEncodeProfile::route('/{record}/edit'),
        ];
    }
}
