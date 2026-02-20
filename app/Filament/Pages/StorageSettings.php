<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Services\AdminLogger;
use App\Services\StorageManager;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

use App\Filament\Concerns\HasCustomizableNavigation;

class StorageSettings extends Page implements HasForms
{
    use HasCustomizableNavigation;
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cloud';
    protected static ?string $navigationLabel = 'Storage & CDN';
    protected static ?string $navigationGroup = 'System';
    protected static ?int $navigationSort = 2;
    protected static string $view = 'filament.pages.site-settings';

    public ?array $data = [];
    public ?string $connectionStatus = null;

    public function mount(): void
    {
        $this->form->fill([
            // Cloud Offloading
            'cloud_offloading_enabled' => Setting::get('cloud_offloading_enabled', false),
            'cloud_offloading_delete_local' => Setting::get('cloud_offloading_delete_local', false),
            'cloud_storage_public_bucket' => Setting::get('cloud_storage_public_bucket', false),
            'cloud_url_expiry_minutes' => Setting::get('cloud_url_expiry_minutes', 120),
            'storage_driver' => Setting::get('storage_driver', 'local'),
            // Wasabi
            'wasabi_enabled' => Setting::get('wasabi_enabled', false),
            'wasabi_key' => Setting::getDecrypted('wasabi_key', ''),
            'wasabi_secret' => Setting::getDecrypted('wasabi_secret', ''),
            'wasabi_region' => Setting::get('wasabi_region', 'us-east-1'),
            'wasabi_bucket' => Setting::get('wasabi_bucket', ''),
            'wasabi_endpoint' => Setting::get('wasabi_endpoint', 'https://s3.wasabisys.com'),
            // Backblaze B2
            'b2_enabled' => Setting::get('b2_enabled', false),
            'b2_key_id' => Setting::getDecrypted('b2_key_id', ''),
            'b2_application_key' => Setting::getDecrypted('b2_application_key', ''),
            'b2_bucket' => Setting::get('b2_bucket', ''),
            'b2_bucket_id' => Setting::get('b2_bucket_id', ''),
            'b2_endpoint' => Setting::get('b2_endpoint', ''),
            // AWS S3
            's3_enabled' => Setting::get('s3_enabled', false),
            's3_key' => Setting::getDecrypted('s3_key', ''),
            's3_secret' => Setting::getDecrypted('s3_secret', ''),
            's3_region' => Setting::get('s3_region', 'us-east-1'),
            's3_bucket' => Setting::get('s3_bucket', ''),
            // CDN
            'cdn_enabled' => Setting::get('cdn_enabled', false),
            'cdn_url' => Setting::get('cdn_url', ''),
            'bunnycdn_enabled' => Setting::get('bunnycdn_enabled', false),
            'bunnycdn_zone' => Setting::get('bunnycdn_zone', ''),
            'bunnycdn_key' => Setting::getDecrypted('bunnycdn_key', ''),
            // FFmpeg
            'ffmpeg_enabled' => Setting::get('ffmpeg_enabled', true),
            'ffmpeg_path' => Setting::get('ffmpeg_path', '/usr/local/bin/ffmpeg'),
            'ffprobe_path' => Setting::get('ffprobe_path', '/usr/local/bin/ffprobe'),
            'ffmpeg_threads' => Setting::get('ffmpeg_threads', 4),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make('Storage Settings')
                    ->tabs([
                        Tabs\Tab::make('General')
                            ->schema([
                                Section::make('Cloud Offloading')
                                    ->description('When enabled, videos are processed locally with FFmpeg then automatically uploaded to your configured cloud storage. The video\'s storage_disk is updated after a successful upload.')
                                    ->schema([
                                        Toggle::make('cloud_offloading_enabled')
                                            ->label('Enable Cloud Offloading')
                                            ->helperText('Automatically upload processed videos, thumbnails, and previews to cloud storage after processing.')
                                            ->reactive()
                                            ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                                if ($state) {
                                                    // Auto-set storage_driver based on which provider is enabled
                                                    if ($get('wasabi_enabled')) {
                                                        $set('storage_driver', 'wasabi');
                                                    } elseif ($get('b2_enabled')) {
                                                        $set('storage_driver', 'b2');
                                                    } elseif ($get('s3_enabled')) {
                                                        $set('storage_driver', 's3');
                                                    }
                                                } else {
                                                    $set('storage_driver', 'local');
                                                }
                                            }),
                                        Select::make('storage_driver')
                                            ->label('Cloud Provider')
                                            ->options([
                                                'local' => 'Local Storage (no offloading)',
                                                'wasabi' => 'Wasabi Cloud Storage',
                                                'b2' => 'Backblaze B2',
                                                's3' => 'Amazon S3',
                                            ])
                                            ->helperText('Which cloud provider to offload files to. Configure credentials in the provider\'s tab first.')
                                            ->visible(fn ($get) => $get('cloud_offloading_enabled')),
                                        Toggle::make('cloud_offloading_delete_local')
                                            ->label('Delete Local Files After Upload')
                                            ->helperText('Remove local copies after successful cloud upload to save disk space. Only enable if your cloud storage is reliable.')
                                            ->visible(fn ($get) => $get('cloud_offloading_enabled')),
                                        Toggle::make('cloud_storage_public_bucket')
                                            ->label('Bucket Has Public Access')
                                            ->helperText('Enable if your bucket policy allows public reads. When off (default), pre-signed temporary URLs are used — this works with private buckets and is more secure.')
                                            ->visible(fn ($get) => $get('cloud_offloading_enabled')),
                                        TextInput::make('cloud_url_expiry_minutes')
                                            ->label('Pre-signed URL Expiry (minutes)')
                                            ->numeric()
                                            ->minValue(5)
                                            ->maxValue(10080)
                                            ->helperText('How long pre-signed URLs remain valid. Only applies when bucket is private. Default: 120 minutes (2 hours).')
                                            ->visible(fn ($get) => $get('cloud_offloading_enabled') && !$get('cloud_storage_public_bucket')),
                                    ]),
                                Section::make('CDN Configuration')
                                    ->schema([
                                        Toggle::make('cdn_enabled')
                                            ->label('Enable CDN'),
                                        TextInput::make('cdn_url')
                                            ->label('CDN URL')
                                            ->url()
                                            ->placeholder('https://cdn.yourdomain.com'),
                                    ])->columns(2),
                            ]),
                        Tabs\Tab::make('Wasabi')
                            ->schema([
                                Section::make('Wasabi Cloud Storage')
                                    ->description('S3-compatible object storage with no egress fees. Endpoint auto-resolves from region.')
                                    ->schema([
                                        Toggle::make('wasabi_enabled')
                                            ->label('Enable Wasabi')
                                            ->columnSpanFull(),
                                        TextInput::make('wasabi_key')
                                            ->label('Access Key')
                                            ->password()
                                            ->revealable(),
                                        TextInput::make('wasabi_secret')
                                            ->label('Secret Key')
                                            ->password()
                                            ->revealable(),
                                        Select::make('wasabi_region')
                                            ->label('Region')
                                            ->options([
                                                'us-east-1' => 'US East 1 (N. Virginia)',
                                                'us-east-2' => 'US East 2 (N. Virginia)',
                                                'us-central-1' => 'US Central 1 (Texas)',
                                                'us-west-1' => 'US West 1 (Oregon)',
                                                'us-west-2' => 'US West 2 (San Jose)',
                                                'ca-central-1' => 'CA Central 1 (Toronto)',
                                                'eu-central-1' => 'EU Central 1 (Amsterdam)',
                                                'eu-central-2' => 'EU Central 2 (Frankfurt)',
                                                'eu-west-1' => 'EU West 1 (London)',
                                                'eu-west-2' => 'EU West 2 (Paris)',
                                                'eu-west-3' => 'EU West 3 (London)',
                                                'eu-south-1' => 'EU South 1 (Milan)',
                                                'ap-northeast-1' => 'AP Northeast 1 (Tokyo)',
                                                'ap-northeast-2' => 'AP Northeast 2 (Osaka)',
                                                'ap-southeast-1' => 'AP Southeast 1 (Singapore)',
                                                'ap-southeast-2' => 'AP Southeast 2 (Sydney)',
                                            ])
                                            ->reactive()
                                            ->afterStateUpdated(function ($state, callable $set) {
                                                $endpoint = StorageManager::getWasabiEndpoint($state ?? 'us-east-1');
                                                $set('wasabi_endpoint', $endpoint);
                                            }),
                                        TextInput::make('wasabi_bucket')
                                            ->label('Bucket Name'),
                                        TextInput::make('wasabi_endpoint')
                                            ->label('Endpoint URL')
                                            ->helperText('Auto-set from region. Override only if needed.')
                                            ->placeholder('https://s3.wasabisys.com'),
                                        Actions::make([
                                            Action::make('testWasabiConnection')
                                                ->label('Test Connection')
                                                ->icon('heroicon-o-signal')
                                                ->color('gray')
                                                ->action(function () {
                                                    $this->testStorageConnection('wasabi');
                                                }),
                                        ])->columnSpanFull(),
                                    ])->columns(2),
                            ]),
                        Tabs\Tab::make('Backblaze B2')
                            ->schema([
                                Section::make('Backblaze B2 Storage')
                                    ->schema([
                                        Toggle::make('b2_enabled')
                                            ->label('Enable Backblaze B2'),
                                        TextInput::make('b2_key_id')
                                            ->label('Application Key ID')
                                            ->password()
                                            ->revealable(),
                                        TextInput::make('b2_application_key')
                                            ->label('Application Key')
                                            ->password()
                                            ->revealable(),
                                        TextInput::make('b2_bucket')
                                            ->label('Bucket Name'),
                                        TextInput::make('b2_bucket_id')
                                            ->label('Bucket ID'),
                                        TextInput::make('b2_endpoint')
                                            ->label('S3 Endpoint')
                                            ->placeholder('https://s3.us-west-001.backblazeb2.com'),
                                    ])->columns(2),
                            ]),
                        Tabs\Tab::make('Amazon S3')
                            ->schema([
                                Section::make('Amazon S3 Storage')
                                    ->schema([
                                        Toggle::make('s3_enabled')
                                            ->label('Enable Amazon S3'),
                                        TextInput::make('s3_key')
                                            ->label('Access Key ID')
                                            ->password()
                                            ->revealable(),
                                        TextInput::make('s3_secret')
                                            ->label('Secret Access Key')
                                            ->password()
                                            ->revealable(),
                                        TextInput::make('s3_region')
                                            ->label('Region')
                                            ->placeholder('us-east-1'),
                                        TextInput::make('s3_bucket')
                                            ->label('Bucket Name'),
                                    ])->columns(2),
                            ]),
                        Tabs\Tab::make('BunnyCDN')
                            ->schema([
                                Section::make('BunnyCDN')
                                    ->schema([
                                        Toggle::make('bunnycdn_enabled')
                                            ->label('Enable BunnyCDN'),
                                        TextInput::make('bunnycdn_zone')
                                            ->label('Pull Zone Name'),
                                        TextInput::make('bunnycdn_key')
                                            ->label('API Key')
                                            ->password()
                                            ->revealable(),
                                    ])->columns(2),
                            ]),
                        Tabs\Tab::make('FFmpeg')
                            ->schema([
                                Section::make('Video Processing')
                                    ->schema([
                                        Toggle::make('ffmpeg_enabled')
                                            ->label('Enable FFmpeg Processing')
                                            ->helperText('Disable if FFmpeg is not installed'),
                                        TextInput::make('ffmpeg_path')
                                            ->label('FFmpeg Binary Path')
                                            ->placeholder('/usr/local/bin/ffmpeg'),
                                        TextInput::make('ffprobe_path')
                                            ->label('FFprobe Binary Path')
                                            ->placeholder('/usr/local/bin/ffprobe'),
                                        TextInput::make('ffmpeg_threads')
                                            ->label('Processing Threads')
                                            ->numeric()
                                            ->minValue(1)
                                            ->maxValue(16),
                                    ])->columns(2),
                            ]),
                    ])->columnSpanFull(),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        // Auto-resolve Wasabi endpoint from region if endpoint is empty or default
        if (!empty($data['wasabi_region'])) {
            $autoEndpoint = StorageManager::getWasabiEndpoint($data['wasabi_region']);
            if (empty($data['wasabi_endpoint']) || $data['wasabi_endpoint'] === 'https://s3.wasabisys.com') {
                $data['wasabi_endpoint'] = $autoEndpoint;
            }
        }

        // If cloud offloading is disabled, force storage_driver to local
        if (empty($data['cloud_offloading_enabled'])) {
            $data['storage_driver'] = 'local';
        }

        // If cloud offloading is enabled but no driver selected, auto-detect
        if (!empty($data['cloud_offloading_enabled']) && ($data['storage_driver'] ?? 'local') === 'local') {
            if (!empty($data['wasabi_enabled'])) {
                $data['storage_driver'] = 'wasabi';
            } elseif (!empty($data['b2_enabled'])) {
                $data['storage_driver'] = 'b2';
            } elseif (!empty($data['s3_enabled'])) {
                $data['storage_driver'] = 's3';
            }
        }

        $encryptedKeys = [
            'wasabi_key', 'wasabi_secret',
            'b2_key_id', 'b2_application_key',
            's3_key', 's3_secret',
            'bunnycdn_key',
        ];

        foreach ($data as $key => $value) {
            if (in_array($key, $encryptedKeys, true)) {
                Setting::setEncrypted($key, $value, 'storage');
                continue;
            }

            $type = match (true) {
                is_bool($value) => 'boolean',
                is_int($value) => 'integer',
                default => 'string',
            };

            Setting::set($key, $value, 'storage', $type);
        }

        AdminLogger::settingsSaved('Storage', array_keys($data));

        Notification::make()
            ->title('Storage settings saved successfully')
            ->success()
            ->send();
    }

    public function testStorageConnection(string $driver = 'wasabi'): void
    {
        // Save current form values first so the test uses latest credentials
        $this->save();

        $result = StorageManager::testConnection($driver);

        if ($result['success']) {
            Notification::make()
                ->title('Connection Successful')
                ->body($result['message'])
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('Connection Failed')
                ->body($result['message'])
                ->danger()
                ->send();
        }
    }
}
