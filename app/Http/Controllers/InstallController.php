<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class InstallController extends Controller
{
    /**
     * Step 1: Requirements check
     */
    public function requirements()
    {
        // Auto-heal common issues before checking requirements
        $this->autoHeal();

        $requirements = $this->checkRequirements();

        return view('install.requirements', compact('requirements'));
    }

    /**
     * Step 2: Database configuration form
     */
    public function database()
    {
        $current = [
            'db_connection' => env('DB_CONNECTION', 'mysql'),
            'db_host' => env('DB_HOST', '127.0.0.1'),
            'db_port' => env('DB_PORT', '3306'),
            'db_database' => env('DB_DATABASE', 'hubtube'),
            'db_username' => env('DB_USERNAME', 'root'),
        ];

        return view('install.database', compact('current'));
    }

    /**
     * Step 2: Save database configuration
     */
    public function saveDatabase(Request $request)
    {
        $validated = $request->validate([
            'db_connection' => 'required|in:mysql,mariadb',
            'db_host' => 'required|string|max:255',
            'db_port' => 'required|integer|min:1|max:65535',
            'db_database' => 'required|string|max:255',
            'db_username' => 'required|string|max:255',
            'db_password' => 'nullable|string|max:255',
        ]);

        $password = $validated['db_password'] ?? '';

        // Test the connection — PDO always uses 'mysql' driver for both MySQL and MariaDB
        try {
            $dsn = "mysql:host={$validated['db_host']};port={$validated['db_port']}";
            $pdo = new \PDO($dsn, $validated['db_username'], $password, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_TIMEOUT => 5,
            ]);
        } catch (\Exception $e) {
            return back()
                ->withInput($request->except('db_password'))
                ->with('db_password_value', $password)
                ->withErrors(['db_connection' => 'Could not connect to MySQL: ' . $e->getMessage()]);
        }

        // Try to create the database if it doesn't exist
        try {
            $dbName = $validated['db_database'];
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `{$dbName}`");
        } catch (\Exception $e) {
            return back()
                ->withInput($request->except('db_password'))
                ->with('db_password_value', $password)
                ->withErrors(['db_connection' => 'Connected to MySQL but failed on database: ' . $e->getMessage()]);
        }

        // Ensure .env is writable before writing
        $this->ensureEnvWritable();

        // Write to .env
        $this->updateEnv([
            'DB_CONNECTION' => $validated['db_connection'],
            'DB_HOST' => $validated['db_host'],
            'DB_PORT' => $validated['db_port'],
            'DB_DATABASE' => $validated['db_database'],
            'DB_USERNAME' => $validated['db_username'],
            'DB_PASSWORD' => $password,
        ]);

        // Clear config cache so Laravel picks up the new DB settings
        try {
            Artisan::call('config:clear');
        } catch (\Exception $e) {
            // Ignore — config cache may not exist
        }

        return redirect()->route('install.application');
    }

    /**
     * Step 3: Application settings
     */
    public function application()
    {
        $current = [
            'app_name' => env('APP_NAME', 'HubTube'),
            'app_url' => env('APP_URL', 'http://localhost'),
            'app_timezone' => env('APP_TIMEZONE', 'UTC'),
            'mail_mailer' => env('MAIL_MAILER', 'log'),
            'mail_host' => env('MAIL_HOST', ''),
            'mail_port' => env('MAIL_PORT', '587'),
            'mail_username' => env('MAIL_USERNAME', ''),
            'mail_from_address' => env('MAIL_FROM_ADDRESS', ''),
            'mail_from_name' => env('MAIL_FROM_NAME', '${APP_NAME}'),
            'mail_encryption' => env('MAIL_ENCRYPTION', 'tls'),
        ];

        $timezones = \DateTimeZone::listIdentifiers();

        return view('install.application', compact('current', 'timezones'));
    }

    /**
     * Step 3: Save application settings
     */
    public function saveApplication(Request $request)
    {
        $validated = $request->validate([
            'app_name' => 'required|string|max:255',
            'app_url' => 'required|url|max:255',
            'app_timezone' => 'required|string|max:255',
            'mail_mailer' => 'required|in:smtp,log,sendmail,ses,postmark,resend',
            'mail_host' => 'nullable|string|max:255',
            'mail_port' => 'nullable|integer|min:1|max:65535',
            'mail_username' => 'nullable|string|max:255',
            'mail_password' => 'nullable|string|max:255',
            'mail_from_address' => 'nullable|email|max:255',
            'mail_encryption' => 'nullable|in:tls,ssl,null',
        ]);

        $envUpdates = [
            'APP_NAME' => $validated['app_name'],
            'APP_URL' => $validated['app_url'],
            'APP_TIMEZONE' => $validated['app_timezone'],
            'VITE_APP_NAME' => '${APP_NAME}',
            'MAIL_MAILER' => $validated['mail_mailer'],
        ];

        // Only write SMTP settings if mailer is smtp
        if ($validated['mail_mailer'] === 'smtp') {
            $envUpdates['MAIL_HOST'] = $validated['mail_host'] ?? '';
            $envUpdates['MAIL_PORT'] = (string) ($validated['mail_port'] ?? '587');
            $envUpdates['MAIL_USERNAME'] = $validated['mail_username'] ?? '';
            $envUpdates['MAIL_PASSWORD'] = $validated['mail_password'] ?? '';
            $envUpdates['MAIL_ENCRYPTION'] = $validated['mail_encryption'] ?? 'tls';
        }

        if (!empty($validated['mail_from_address'])) {
            $envUpdates['MAIL_FROM_ADDRESS'] = $validated['mail_from_address'];
        }

        // Generate APP_KEY if not set
        if (empty(env('APP_KEY'))) {
            Artisan::call('key:generate', ['--force' => true]);
        }

        $this->ensureEnvWritable();
        $this->updateEnv($envUpdates);

        return redirect()->route('install.admin');
    }

    /**
     * Step 4: Admin account creation
     */
    public function admin()
    {
        return view('install.admin');
    }

    /**
     * Step 4: Create admin account
     */
    public function saveAdmin(Request $request)
    {
        $validated = $request->validate([
            'username' => 'required|string|min:3|max:30|regex:/^[a-zA-Z0-9_]+$/',
            'email' => 'required|email|max:255',
            'password' => 'required|string|min:8|confirmed',
        ]);

        // Store in session for finalize step (DB may not have tables yet)
        $request->session()->put('install_admin', $validated);

        return redirect()->route('install.finalize');
    }

    /**
     * Step 5: Finalize — run migrations, seed, create admin
     */
    public function finalize()
    {
        $adminData = session('install_admin');
        if (!$adminData) {
            return redirect()->route('install.admin');
        }

        return view('install.finalize', compact('adminData'));
    }

    /**
     * Step 5: Execute finalization
     */
    public function executeFinalize(Request $request)
    {
        $adminData = session('install_admin');
        if (!$adminData) {
            return redirect()->route('install.admin');
        }

        $steps = [];

        // 0. Fix open_basedir if needed
        $openBasedirResult = $this->fixOpenBasedir();
        if ($openBasedirResult) {
            $isFixed = str_starts_with($openBasedirResult, 'Fixed');
            $steps[] = ['label' => 'Fix open_basedir', 'status' => $isFixed ? 'success' : 'warning', 'message' => $openBasedirResult];
        }

        // 1. Run migrations (with retry logic for "table already exists")
        try {
            Artisan::call('migrate', ['--force' => true]);
            $steps[] = ['label' => 'Database migrations', 'status' => 'success'];
        } catch (\Exception $e) {
            // If table already exists, try migrate:fresh (only safe during installation)
            if (str_contains($e->getMessage(), 'already exists')) {
                try {
                    Artisan::call('migrate:fresh', ['--force' => true]);
                    $steps[] = ['label' => 'Database migrations', 'status' => 'success', 'message' => 'Ran fresh migration (previous tables were cleared)'];
                } catch (\Exception $e2) {
                    $steps[] = ['label' => 'Database migrations', 'status' => 'error', 'message' => $e2->getMessage()];
                    return view('install.finalize', ['adminData' => $adminData, 'steps' => $steps, 'failed' => true]);
                }
            } else {
                $steps[] = ['label' => 'Database migrations', 'status' => 'error', 'message' => $e->getMessage()];
                return view('install.finalize', ['adminData' => $adminData, 'steps' => $steps, 'failed' => true]);
            }
        }

        // 2. Seed categories, gifts, settings
        try {
            Artisan::call('db:seed', ['--class' => 'CategorySeeder', '--force' => true]);
            Artisan::call('db:seed', ['--class' => 'GiftSeeder', '--force' => true]);
            Artisan::call('db:seed', ['--class' => 'SettingsSeeder', '--force' => true]);
            Artisan::call('db:seed', ['--class' => 'PageSeeder', '--force' => true]);
            $steps[] = ['label' => 'Seed default data', 'status' => 'success'];
        } catch (\Exception $e) {
            $steps[] = ['label' => 'Seed default data', 'status' => 'error', 'message' => $e->getMessage()];
            return view('install.finalize', ['adminData' => $adminData, 'steps' => $steps, 'failed' => true]);
        }

        // 3. Create admin user
        try {
            $admin = \App\Models\User::where('email', $adminData['email'])->first();
            if (!$admin) {
                $admin = new \App\Models\User();
                $admin->forceFill([
                    'username' => $adminData['username'],
                    'email' => $adminData['email'],
                    'password' => Hash::make($adminData['password']),
                    'email_verified_at' => now(),
                    'is_admin' => true,
                    'is_verified' => true,
                    'is_pro' => true,
                    'age_verified_at' => now(),
                ])->save();
            }

            // Create channel for admin
            \App\Models\Channel::firstOrCreate(
                ['user_id' => $admin->id],
                [
                    'name' => $adminData['username'],
                    'slug' => Str::slug($adminData['username']) . '-' . $admin->id,
                    'is_verified' => true,
                ]
            );

            $steps[] = ['label' => 'Create admin account', 'status' => 'success'];
        } catch (\Exception $e) {
            $steps[] = ['label' => 'Create admin account', 'status' => 'error', 'message' => $e->getMessage()];
            return view('install.finalize', ['adminData' => $adminData, 'steps' => $steps, 'failed' => true]);
        }

        // 4. Create storage symlink
        try {
            Artisan::call('storage:link', ['--force' => true]);
            $steps[] = ['label' => 'Create storage link', 'status' => 'success'];
        } catch (\Exception $e) {
            $steps[] = ['label' => 'Create storage link', 'status' => 'warning', 'message' => $e->getMessage()];
        }

        // 5. Publish Filament & Livewire assets (critical for admin panel)
        try {
            $assetResults = $this->publishAssets();
            $hasFilament = is_dir(public_path('vendor/filament'));
            $steps[] = [
                'label' => 'Publish admin panel assets',
                'status' => $hasFilament ? 'success' : 'warning',
                'message' => $hasFilament ? null : 'Filament assets may need manual publishing. Run: php artisan filament:assets',
            ];
        } catch (\Exception $e) {
            $steps[] = ['label' => 'Publish admin panel assets', 'status' => 'warning', 'message' => $e->getMessage()];
        }

        // 6. Clear and optimize caches
        try {
            Artisan::call('config:clear');
            Artisan::call('cache:clear');
            Artisan::call('view:clear');
            Artisan::call('route:clear');
            $steps[] = ['label' => 'Clear caches', 'status' => 'success'];
        } catch (\Exception $e) {
            $steps[] = ['label' => 'Clear caches', 'status' => 'warning', 'message' => $e->getMessage()];
        }

        // 7. Detect environment and show helpful info
        $environment = $this->detectEnvironment();

        // 8. Mark as installed
        File::put(storage_path('installed'), now()->toDateTimeString());
        $steps[] = ['label' => 'Mark installation complete', 'status' => 'success'];

        // Clear session
        $request->session()->forget('install_admin');

        return view('install.complete', compact('steps', 'adminData', 'environment'));
    }

    /**
     * Auto-heal common issues that trip up novice users.
     */
    protected function autoHeal(): void
    {
        // Fix open_basedir restriction (aaPanel, cPanel, etc.)
        $this->fixOpenBasedir();

        // Create .env from .env.example if missing
        if (!File::exists(base_path('.env')) && File::exists(base_path('.env.example'))) {
            File::copy(base_path('.env.example'), base_path('.env'));
            try {
                Artisan::call('key:generate', ['--force' => true]);
            } catch (\Exception $e) {
                // Will be generated later
            }
        }

        // Generate APP_KEY if empty
        if (File::exists(base_path('.env')) && empty(env('APP_KEY'))) {
            try {
                Artisan::call('key:generate', ['--force' => true]);
            } catch (\Exception $e) {
                // Will be generated later
            }
        }

        // Create all required storage directories
        $dirs = [
            storage_path('app/public'),
            storage_path('framework/cache/data'),
            storage_path('framework/sessions'),
            storage_path('framework/views'),
            storage_path('logs'),
            base_path('bootstrap/cache'),
            public_path('vendor'),
        ];

        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
        }

        // Fix .env permissions
        $this->ensureEnvWritable();

        // Auto-detect and fix Redis password
        $redisPassword = env('REDIS_PASSWORD');
        if (empty($redisPassword) || $redisPassword === 'null') {
            $detected = $this->detectRedisPassword();
            if ($detected) {
                $this->ensureEnvWritable();
                $this->updateEnv(['REDIS_PASSWORD' => $detected]);
            }
        }
    }

    /**
     * Ensure .env file exists and is writable by the web server.
     */
    protected function ensureEnvWritable(): void
    {
        $envPath = base_path('.env');
        if (File::exists($envPath) && !is_writable($envPath)) {
            @chmod($envPath, 0664);
        }
    }

    /**
     * Check system requirements
     */
    protected function checkRequirements(): array
    {
        $phpVersion = PHP_VERSION;
        $phpOk = version_compare($phpVersion, '8.2.0', '>=');

        $extensions = [
            'pdo' => extension_loaded('pdo'),
            'pdo_mysql' => extension_loaded('pdo_mysql'),
            'mbstring' => extension_loaded('mbstring'),
            'openssl' => extension_loaded('openssl'),
            'tokenizer' => extension_loaded('tokenizer'),
            'json' => extension_loaded('json'),
            'curl' => extension_loaded('curl'),
            'fileinfo' => extension_loaded('fileinfo'),
            'gd' => extension_loaded('gd'),
            'xml' => extension_loaded('xml'),
            'bcmath' => extension_loaded('bcmath'),
            'ctype' => extension_loaded('ctype'),
            'redis' => extension_loaded('redis'),
        ];

        $optionalExtensions = [
            'redis' => extension_loaded('redis'),
            'imagick' => extension_loaded('imagick'),
            'zip' => extension_loaded('zip'),
            'exif' => extension_loaded('exif'),
        ];

        $directories = [
            'storage/app' => is_writable(storage_path('app')),
            'storage/framework' => is_writable(storage_path('framework')),
            'storage/logs' => is_writable(storage_path('logs')),
            'bootstrap/cache' => is_writable(base_path('bootstrap/cache')),
            'public' => is_writable(public_path()),
        ];

        $envExists = File::exists(base_path('.env'));
        $envWritable = $envExists && is_writable(base_path('.env'));

        // Check for FFmpeg
        $ffmpegPath = file_exists('/usr/local/bin/ffmpeg')
            ? '/usr/local/bin/ffmpeg'
            : trim(shell_exec('which ffmpeg 2>/dev/null') ?? '');
        $ffmpegInstalled = !empty($ffmpegPath) && is_executable($ffmpegPath);

        // Check for Node.js
        $nodePath = trim(shell_exec('which node 2>/dev/null') ?? '');
        $nodeInstalled = !empty($nodePath);
        $nodeVersion = $nodeInstalled ? trim(shell_exec('node --version 2>/dev/null') ?? '') : '';

        // Check for Composer
        $composerPath = trim(shell_exec('which composer 2>/dev/null') ?? '');
        $composerInstalled = !empty($composerPath);

        // Check MySQL/MariaDB connectivity
        $mysqlAvailable = false;
        $mysqlVersion = '';
        try {
            $pdo = new \PDO(
                'mysql:host=' . env('DB_HOST', '127.0.0.1') . ';port=' . env('DB_PORT', '3306'),
                env('DB_USERNAME', 'root'),
                env('DB_PASSWORD', ''),
                [\PDO::ATTR_TIMEOUT => 2]
            );
            $mysqlAvailable = true;
            $mysqlVersion = $pdo->getAttribute(\PDO::ATTR_SERVER_VERSION);
        } catch (\Exception $e) {
            // Also try connecting without credentials (socket auth)
            try {
                $pdo = new \PDO('mysql:host=127.0.0.1;port=3306', 'root', '', [\PDO::ATTR_TIMEOUT => 2]);
                $mysqlAvailable = true;
                $mysqlVersion = $pdo->getAttribute(\PDO::ATTR_SERVER_VERSION);
            } catch (\Exception $e2) {
                $mysqlAvailable = false;
            }
        }

        // Check Redis connectivity (with auth detection)
        $redisAvailable = false;
        $redisNeedsAuth = false;
        $redisPassword = env('REDIS_PASSWORD');
        try {
            $redis = new \Redis();
            $connected = @$redis->connect(
                env('REDIS_HOST', '127.0.0.1'),
                (int) env('REDIS_PORT', 6379),
                1.0
            );
            if ($connected) {
                // Try ping — if Redis has a password and we don't have it, this fails with NOAUTH
                try {
                    $redis->ping();
                    $redisAvailable = true;
                } catch (\Exception $authEx) {
                    if (str_contains($authEx->getMessage(), 'NOAUTH') || str_contains($authEx->getMessage(), 'AUTH')) {
                        $redisNeedsAuth = true;
                        // Auto-detect Redis password from common config locations
                        $detectedPassword = $this->detectRedisPassword();
                        if ($detectedPassword) {
                            try {
                                $redis->auth($detectedPassword);
                                $redis->ping();
                                $redisAvailable = true;
                                $redisPassword = $detectedPassword;
                                // Auto-fix .env with detected password
                                $this->ensureEnvWritable();
                                $this->updateEnv(['REDIS_PASSWORD' => $detectedPassword]);
                            } catch (\Exception $e2) {
                                $redisAvailable = false;
                            }
                        }
                    }
                }
                $redis->close();
            }
        } catch (\Exception $e) {
            $redisAvailable = false;
        }

        $allExtensionsOk = !in_array(false, array_diff_key($extensions, ['redis' => true]));
        $allDirsOk = !in_array(false, $directories);
        $canProceed = $phpOk && $allExtensionsOk && $allDirsOk && $envExists;

        return [
            'php_version' => $phpVersion,
            'php_ok' => $phpOk,
            'extensions' => $extensions,
            'optional_extensions' => $optionalExtensions,
            'directories' => $directories,
            'env_exists' => $envExists,
            'env_writable' => $envWritable,
            'ffmpeg_installed' => $ffmpegInstalled,
            'ffmpeg_path' => $ffmpegPath,
            'node_installed' => $nodeInstalled,
            'node_version' => $nodeVersion,
            'composer_installed' => $composerInstalled,
            'mysql_available' => $mysqlAvailable,
            'mysql_version' => $mysqlVersion,
            'redis_available' => $redisAvailable,
            'redis_needs_auth' => $redisNeedsAuth && !$redisAvailable,
            'can_proceed' => $canProceed,
        ];
    }

    /**
     * Detect Redis password from common config file locations.
     * Supports aaPanel, system redis, and other common setups.
     */
    protected function detectRedisPassword(): ?string
    {
        $configPaths = [
            '/www/server/redis/redis.conf',       // aaPanel
            '/etc/redis/redis.conf',              // Ubuntu/Debian default
            '/etc/redis.conf',                    // CentOS/RHEL
            '/usr/local/etc/redis/redis.conf',    // FreeBSD / Homebrew
            '/www/server/redis/etc/redis.conf',   // aaPanel alternate
        ];

        foreach ($configPaths as $path) {
            if (!is_readable($path)) {
                continue;
            }
            $contents = @file_get_contents($path);
            if (!$contents) {
                continue;
            }
            // Match "requirepass <password>" (not commented out)
            if (preg_match('/^\s*requirepass\s+(\S+)/m', $contents, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    /**
     * Detect the hosting environment (aaPanel, cPanel, Webmin, bare metal).
     */
    protected function detectEnvironment(): array
    {
        $env = [
            'panel' => 'none',
            'web_user' => 'www-data',
            'php_socket' => null,
            'open_basedir' => ini_get('open_basedir') ?: null,
        ];

        // aaPanel
        if (is_dir('/www/server/panel')) {
            $env['panel'] = 'aapanel';
            $env['web_user'] = 'www';
            // Detect PHP-FPM socket
            $phpVer = PHP_MAJOR_VERSION . PHP_MINOR_VERSION;
            $sock = "/tmp/php-cgi-{$phpVer}.sock";
            if (file_exists($sock)) {
                $env['php_socket'] = $sock;
            }
        }
        // cPanel
        elseif (is_dir('/usr/local/cpanel') || is_dir('/var/cpanel')) {
            $env['panel'] = 'cpanel';
            $env['web_user'] = 'nobody';
        }
        // Webmin/Virtualmin
        elseif (is_dir('/etc/webmin')) {
            $env['panel'] = 'webmin';
        }
        // Plesk
        elseif (is_dir('/usr/local/psa') || is_dir('/opt/psa')) {
            $env['panel'] = 'plesk';
        }

        return $env;
    }

    /**
     * Publish Filament and Livewire assets to public/vendor/.
     * This is critical for the admin panel to work.
     */
    protected function publishAssets(): array
    {
        $results = [];

        // Ensure public/vendor directory exists
        $vendorPath = public_path('vendor');
        if (!is_dir($vendorPath)) {
            @mkdir($vendorPath, 0775, true);
        }

        // Publish Filament assets via Artisan
        try {
            Artisan::call('filament:assets');
            $results[] = 'Filament assets published';
        } catch (\Exception $e) {
            $results[] = 'Filament assets failed: ' . $e->getMessage();
        }

        // Publish Livewire assets
        try {
            Artisan::call('vendor:publish', ['--tag' => 'livewire:assets', '--force' => true]);
            $results[] = 'Livewire assets published';
        } catch (\Exception $e) {
            // Livewire may serve assets via route instead of published files
        }

        // Publish Laravel assets
        try {
            Artisan::call('vendor:publish', ['--tag' => 'laravel-assets', '--force' => true]);
        } catch (\Exception $e) {
            // Non-critical
        }

        // Filament icons
        try {
            Artisan::call('icons:cache');
        } catch (\Exception $e) {
            // Non-critical
        }

        // If Filament assets still don't exist, try to manually copy from vendor
        $filamentAssetPath = public_path('vendor/filament');
        if (!is_dir($filamentAssetPath)) {
            $this->copyFilamentAssetsManually();
        }

        return $results;
    }

    /**
     * Manually copy Filament assets from vendor directory as a fallback.
     */
    protected function copyFilamentAssetsManually(): void
    {
        $sourceBase = base_path('vendor/filament');
        $destBase = public_path('vendor/filament');

        if (!is_dir($sourceBase)) {
            return;
        }

        // Find all public asset directories in filament packages
        $packages = ['filament', 'forms', 'tables', 'support', 'actions', 'infolists', 'notifications', 'widgets'];
        foreach ($packages as $package) {
            $publicDir = "{$sourceBase}/{$package}/resources/dist";
            if (!is_dir($publicDir)) {
                $publicDir = "{$sourceBase}/{$package}/dist";
            }
            if (is_dir($publicDir)) {
                $dest = "{$destBase}/{$package}";
                if (!is_dir($dest)) {
                    @mkdir($dest, 0775, true);
                }
                $this->recursiveCopy($publicDir, $dest);
            }
        }

        // Also handle Livewire
        $livewireSrc = base_path('vendor/livewire/livewire/dist');
        $livewireDest = public_path('vendor/livewire');
        if (is_dir($livewireSrc) && !is_dir($livewireDest)) {
            @mkdir($livewireDest, 0775, true);
            $this->recursiveCopy($livewireSrc, $livewireDest);
        }
    }

    /**
     * Recursively copy a directory.
     */
    protected function recursiveCopy(string $src, string $dst): void
    {
        $dir = opendir($src);
        if (!$dir) return;
        @mkdir($dst, 0775, true);
        while (($file = readdir($dir)) !== false) {
            if ($file === '.' || $file === '..') continue;
            $srcPath = $src . '/' . $file;
            $dstPath = $dst . '/' . $file;
            if (is_dir($srcPath)) {
                $this->recursiveCopy($srcPath, $dstPath);
            } else {
                @copy($srcPath, $dstPath);
            }
        }
        closedir($dir);
    }

    /**
     * Fix open_basedir restriction for aaPanel and similar panels.
     * Attempts to update .user.ini to include the full project root.
     */
    protected function fixOpenBasedir(): ?string
    {
        $openBasedir = ini_get('open_basedir');
        if (empty($openBasedir)) {
            return null; // No restriction
        }

        $projectRoot = base_path();
        $publicRoot = public_path();

        // Check if project root is already allowed
        $paths = explode(PATH_SEPARATOR, $openBasedir);
        foreach ($paths as $path) {
            $path = rtrim($path, '/');
            if ($path === $projectRoot || $path === rtrim($projectRoot, '/')) {
                return null; // Already allowed
            }
        }

        // If only public/ is allowed, we need to fix it
        $userIniPath = public_path('.user.ini');
        if (file_exists($userIniPath)) {
            // Try to remove immutable flag (aaPanel sets this)
            @exec('chattr -i ' . escapeshellarg($userIniPath) . ' 2>/dev/null');

            $content = @file_get_contents($userIniPath);
            if ($content !== false) {
                // Replace the open_basedir line to include full project root
                $newBasedir = $projectRoot . ':/tmp/:/proc/';
                $newContent = preg_replace(
                    '/^open_basedir\s*=.*/m',
                    "open_basedir={$newBasedir}",
                    $content
                );
                if ($newContent !== $content) {
                    @file_put_contents($userIniPath, $newContent);
                    return "Fixed open_basedir in .user.ini";
                }
            }
        }

        return "open_basedir restriction detected: {$openBasedir}. You may need to update your hosting panel to allow access to the full project directory.";
    }

    /**
     * Update .env file values
     */
    protected function updateEnv(array $values): void
    {
        $envPath = base_path('.env');
        $envContent = File::get($envPath);

        foreach ($values as $key => $value) {
            // Always quote passwords and values with special characters
            $needsQuotes = $value === ''
                || str_contains($value, ' ')
                || str_contains($value, '#')
                || str_contains($value, '!')
                || str_contains($value, '$')
                || str_contains($value, '"')
                || str_contains($value, '\\')
                || $key === 'DB_PASSWORD'
                || $key === 'MAIL_PASSWORD';

            $formatted = $needsQuotes ? '"' . addcslashes($value, '"\\') . '"' : $value;

            // Use preg_replace_callback to avoid replacement string escaping issues
            $pattern = "/^" . preg_quote($key, '/') . "=.*/m";
            if (preg_match($pattern, $envContent)) {
                $envContent = preg_replace($pattern, "{$key}={$formatted}", $envContent);
            } else {
                $envContent .= "\n{$key}={$formatted}";
            }
        }

        File::put($envPath, $envContent);
    }
}
