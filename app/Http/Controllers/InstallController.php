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

        // Test the connection
        try {
            $pdo = new \PDO(
                "mysql:host={$validated['db_host']};port={$validated['db_port']}",
                $validated['db_username'],
                $validated['db_password'] ?? ''
            );
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            // Try to create the database if it doesn't exist
            $dbName = $validated['db_database'];
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

            // Test connecting to the actual database
            $pdo->exec("USE `{$dbName}`");
        } catch (\Exception $e) {
            return back()->withInput()->withErrors([
                'db_connection' => 'Could not connect to database: ' . $e->getMessage(),
            ]);
        }

        // Write to .env
        $this->updateEnv([
            'DB_CONNECTION' => $validated['db_connection'],
            'DB_HOST' => $validated['db_host'],
            'DB_PORT' => $validated['db_port'],
            'DB_DATABASE' => $validated['db_database'],
            'DB_USERNAME' => $validated['db_username'],
            'DB_PASSWORD' => $validated['db_password'] ?? '',
        ]);

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

        try {
            // 1. Run migrations
            Artisan::call('migrate', ['--force' => true]);
            $steps[] = ['label' => 'Database migrations', 'status' => 'success'];
        } catch (\Exception $e) {
            $steps[] = ['label' => 'Database migrations', 'status' => 'error', 'message' => $e->getMessage()];
            return view('install.finalize', ['adminData' => $adminData, 'steps' => $steps, 'failed' => true]);
        }

        try {
            // 2. Seed categories, gifts, settings
            Artisan::call('db:seed', ['--class' => 'CategorySeeder', '--force' => true]);
            Artisan::call('db:seed', ['--class' => 'GiftSeeder', '--force' => true]);
            Artisan::call('db:seed', ['--class' => 'SettingsSeeder', '--force' => true]);
            $steps[] = ['label' => 'Seed default data', 'status' => 'success'];
        } catch (\Exception $e) {
            $steps[] = ['label' => 'Seed default data', 'status' => 'error', 'message' => $e->getMessage()];
            return view('install.finalize', ['adminData' => $adminData, 'steps' => $steps, 'failed' => true]);
        }

        try {
            // 3. Create admin user
            $userModel = app(\App\Models\User::class);
            $admin = $userModel::firstOrCreate(
                ['email' => $adminData['email']],
                [
                    'username' => $adminData['username'],
                    'password' => Hash::make($adminData['password']),
                    'email_verified_at' => now(),
                    'is_admin' => true,
                    'is_verified' => true,
                    'is_pro' => true,
                    'age_verified_at' => now(),
                ]
            );

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

        try {
            // 4. Create storage symlink
            Artisan::call('storage:link', ['--force' => true]);
            $steps[] = ['label' => 'Create storage link', 'status' => 'success'];
        } catch (\Exception $e) {
            $steps[] = ['label' => 'Create storage link', 'status' => 'warning', 'message' => $e->getMessage()];
        }

        try {
            // 5. Clear caches
            Artisan::call('config:clear');
            Artisan::call('cache:clear');
            Artisan::call('view:clear');
            Artisan::call('route:clear');
            $steps[] = ['label' => 'Clear caches', 'status' => 'success'];
        } catch (\Exception $e) {
            $steps[] = ['label' => 'Clear caches', 'status' => 'warning', 'message' => $e->getMessage()];
        }

        // 6. Mark as installed
        File::put(storage_path('installed'), now()->toDateTimeString());
        $steps[] = ['label' => 'Mark installation complete', 'status' => 'success'];

        // Clear session
        $request->session()->forget('install_admin');

        return view('install.complete', compact('steps', 'adminData'));
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
        $ffmpegPath = trim(shell_exec('which ffmpeg 2>/dev/null') ?? '');
        $ffmpegInstalled = !empty($ffmpegPath);

        // Check for Node.js
        $nodePath = trim(shell_exec('which node 2>/dev/null') ?? '');
        $nodeInstalled = !empty($nodePath);
        $nodeVersion = $nodeInstalled ? trim(shell_exec('node --version 2>/dev/null') ?? '') : '';

        // Check for Composer
        $composerPath = trim(shell_exec('which composer 2>/dev/null') ?? '');
        $composerInstalled = !empty($composerPath);

        // Check Redis connectivity
        $redisAvailable = false;
        try {
            $redis = new \Redis();
            $redisAvailable = @$redis->connect(
                env('REDIS_HOST', '127.0.0.1'),
                (int) env('REDIS_PORT', 6379),
                1.0 // 1 second timeout
            );
            if ($redisAvailable) {
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
            'redis_available' => $redisAvailable,
            'can_proceed' => $canProceed,
        ];
    }

    /**
     * Update .env file values
     */
    protected function updateEnv(array $values): void
    {
        $envPath = base_path('.env');
        $envContent = File::get($envPath);

        foreach ($values as $key => $value) {
            // Quote values with spaces
            $formatted = $value;
            if (str_contains($value, ' ') || str_contains($value, '#') || $value === '') {
                $formatted = '"' . $value . '"';
            }

            if (preg_match("/^{$key}=.*/m", $envContent)) {
                $envContent = preg_replace("/^{$key}=.*/m", "{$key}={$formatted}", $envContent);
            } else {
                $envContent .= "\n{$key}={$formatted}";
            }
        }

        File::put($envPath, $envContent);
    }
}
