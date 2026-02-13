<?php

namespace App\Services;

use App\Models\Channel;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WordPressUserImportService
{
    // WP table prefix from the SQL dump
    private string $tablePrefix = 'MKdOzH8c_';

    // Parsed users from the SQL file
    private array $users = [];

    // Column order in the WP users table INSERT statement
    private const USER_COLUMNS = [
        'ID', 'user_login', 'user_pass', 'user_nicename',
        'user_email', 'user_url', 'user_registered',
        'user_activation_key', 'user_status', 'display_name',
    ];

    /**
     * Parse the SQL file and extract all user rows.
     * Returns stats about what was found.
     */
    public function parseSqlFile(string $filePath): array
    {
        $this->users = [];

        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new \RuntimeException("Cannot open SQL file: {$filePath}");
        }

        $currentTable = null;

        while (($line = fgets($handle)) !== false) {
            $trimmed = trim($line);

            // Skip empty lines, comments, and non-data statements
            if ($trimmed === '' || str_starts_with($trimmed, '--') || str_starts_with($trimmed, '/*')) {
                continue;
            }

            // Detect INSERT INTO for the users table
            if (preg_match('/^INSERT INTO `' . preg_quote($this->tablePrefix, '/') . 'users`/', $trimmed)) {
                $currentTable = 'users';
                $this->processLine($trimmed);
                continue;
            }

            // Continuation rows within the same INSERT statement
            if ($currentTable === 'users' && str_starts_with($trimmed, '(')) {
                $this->processLine($trimmed);
                continue;
            }

            // Any other statement resets context
            if (preg_match('/^(CREATE|ALTER|DROP|LOCK|UNLOCK|SET|START|COMMIT)/', $trimmed)) {
                $currentTable = null;
            }
        }

        fclose($handle);

        return [
            'total_users' => count($this->users),
            'with_email' => count(array_filter($this->users, fn($u) => !empty($u['user_email']))),
            'date_range' => $this->getDateRange(),
        ];
    }

    /**
     * Get the parsed users ready for import.
     */
    public function getUsers(): array
    {
        return $this->users;
    }

    /**
     * Get date range of registrations.
     */
    private function getDateRange(): array
    {
        if (empty($this->users)) {
            return ['earliest' => null, 'latest' => null];
        }

        $dates = array_filter(array_column($this->users, 'user_registered'), fn($d) => $d && $d !== '0000-00-00 00:00:00');
        if (empty($dates)) {
            return ['earliest' => null, 'latest' => null];
        }

        sort($dates);
        return [
            'earliest' => $dates[0],
            'latest' => end($dates),
        ];
    }

    /**
     * Process a single line containing one or more row tuples.
     */
    private function processLine(string $line): void
    {
        $colCount = count(self::USER_COLUMNS);
        $offset = 0;
        $len = strlen($line);

        while ($offset < $len) {
            $start = strpos($line, '(', $offset);
            if ($start === false) break;

            $end = 0;
            $values = $this->parseTuple($line, $start, $end);
            if ($values === null) break;

            $offset = $end + 1;

            if (count($values) !== $colCount) continue;

            $row = array_combine(self::USER_COLUMNS, $values);
            $this->users[(int) $row['ID']] = $row;
        }
    }

    /**
     * Parse a single SQL value tuple starting at $start.
     * Reused from WordPressImportService with identical logic.
     */
    private function parseTuple(string $buffer, int $start, int &$end): ?array
    {
        $len = strlen($buffer);
        $i = $start + 1; // skip opening '('
        $values = [];
        $current = '';
        $inString = false;
        $stringChar = '';
        $depth = 0;

        while ($i < $len) {
            $char = $buffer[$i];

            if ($inString) {
                if ($char === '\\' && $i + 1 < $len) {
                    $current .= $buffer[$i + 1];
                    $i += 2;
                    continue;
                }
                if ($char === $stringChar) {
                    if ($i + 1 < $len && $buffer[$i + 1] === $stringChar) {
                        $current .= $stringChar;
                        $i += 2;
                        continue;
                    }
                    $inString = false;
                    $i++;
                    continue;
                }
                $current .= $char;
                $i++;
                continue;
            }

            if ($char === '\'' || $char === '"') {
                $inString = true;
                $stringChar = $char;
                $i++;
                continue;
            }

            if ($char === '(') {
                $depth++;
                $current .= $char;
                $i++;
                continue;
            }

            if ($char === ')') {
                if ($depth > 0) {
                    $depth--;
                    $current .= $char;
                    $i++;
                    continue;
                }
                $values[] = trim($current) === 'NULL' ? null : trim($current);
                $end = $i;
                return $values;
            }

            if ($char === ',' && $depth === 0) {
                $values[] = trim($current) === 'NULL' ? null : trim($current);
                $current = '';
                $i++;
                continue;
            }

            $current .= $char;
            $i++;
        }

        $end = $len;
        return null;
    }

    /**
     * Sanitize a WordPress username for HubTube.
     * HubTube requires: [a-zA-Z0-9_], 5-32 chars.
     */
    private function sanitizeUsername(string $wpLogin): string
    {
        // Replace any non-alphanumeric/underscore chars with underscore
        $clean = preg_replace('/[^a-zA-Z0-9_]/', '_', $wpLogin);

        // Collapse multiple underscores
        $clean = preg_replace('/_+/', '_', $clean);

        // Trim underscores from edges
        $clean = trim($clean, '_');

        // Ensure minimum length of 5
        if (strlen($clean) < 5) {
            $clean = $clean . str_repeat('_', 5 - strlen($clean));
        }

        // Truncate to 32 chars
        $clean = substr($clean, 0, 32);

        return $clean;
    }

    /**
     * Generate a unique username that doesn't conflict with existing users.
     */
    private function generateUniqueUsername(string $baseUsername, array &$usedUsernames): string
    {
        $username = $baseUsername;
        $suffix = 2;

        while (isset($usedUsernames[strtolower($username)]) || User::where('username', $username)->exists()) {
            $suffixStr = '_' . $suffix;
            $username = substr($baseUsername, 0, 32 - strlen($suffixStr)) . $suffixStr;
            $suffix++;
        }

        $usedUsernames[strtolower($username)] = true;
        return $username;
    }

    /**
     * Import a batch of WP users into HubTube.
     * Uses DB::table() directly to bypass Eloquent's hashed cast and LogsActivity trait,
     * storing the original WP password hash in a single INSERT (no bcrypt overhead).
     */
    public function importBatch(array $wpUsers, array &$usedUsernames): array
    {
        $imported = 0;
        $skipped = 0;
        $errors = [];

        // Pre-load existing emails for this batch to check duplicates
        $batchEmails = array_filter(array_map(fn($u) => strtolower(trim($u['user_email'] ?? '')), $wpUsers));
        $existingEmails = DB::table('users')
            ->whereIn('email', $batchEmails)
            ->pluck('email')
            ->map(fn($e) => strtolower($e))
            ->flip()
            ->all();

        $now = now();

        foreach ($wpUsers as $wpUser) {
            try {
                $email = strtolower(trim($wpUser['user_email'] ?? ''));

                // Skip users without email
                if (empty($email)) {
                    $skipped++;
                    continue;
                }

                // Skip if email already exists
                if (isset($existingEmails[$email])) {
                    $skipped++;
                    continue;
                }

                // Sanitize and deduplicate username
                $baseUsername = $this->sanitizeUsername($wpUser['user_login'] ?? 'wp_user');
                $username = $this->generateUniqueUsername($baseUsername, $usedUsernames);

                // Parse display name for first/last name
                $displayName = trim($wpUser['display_name'] ?? '');
                $firstName = null;
                $lastName = null;
                if ($displayName && str_contains($displayName, ' ')) {
                    $parts = explode(' ', $displayName, 2);
                    $firstName = $parts[0];
                    $lastName = $parts[1] ?? null;
                } elseif ($displayName) {
                    $firstName = $displayName;
                }

                // Registration date
                $registeredAt = null;
                $wpDate = $wpUser['user_registered'] ?? null;
                if ($wpDate && $wpDate !== '0000-00-00 00:00:00') {
                    try {
                        $registeredAt = \Carbon\Carbon::parse($wpDate);
                    } catch (\Throwable $e) {
                        $registeredAt = null;
                    }
                }

                // Store the WP password hash directly — no Hash::make() overhead
                $wpHash = $wpUser['user_pass'] ?? '';

                // Insert user directly via DB::table to bypass:
                // - Eloquent's 'hashed' cast (avoids expensive Hash::make calls)
                // - LogsActivity trait (avoids activity_log inserts per user)
                $userId = DB::table('users')->insertGetId([
                    'username' => $username,
                    'email' => $email,
                    'password' => !empty($wpHash) ? $wpHash : Hash::make(Str::random(16)),
                    'first_name' => $firstName ? substr($firstName, 0, 50) : null,
                    'last_name' => $lastName ? substr($lastName, 0, 50) : null,
                    'email_verified_at' => $registeredAt ?? $now,
                    'settings' => json_encode(['wp_imported' => true, 'wp_user_id' => (int) $wpUser['ID']]),
                    'created_at' => $registeredAt ?? $now,
                    'updated_at' => $now,
                ]);

                // Create a channel for the user
                $baseSlug = Str::slug($username) ?: 'channel';
                $channelSlug = $baseSlug . '-' . $userId;
                $suffix = 2;
                while (DB::table('channels')->where('slug', $channelSlug)->exists()) {
                    $channelSlug = $baseSlug . '-' . $userId . '-' . $suffix;
                    $suffix++;
                }

                DB::table('channels')->insert([
                    'user_id' => $userId,
                    'name' => $displayName ?: $username,
                    'slug' => $channelSlug,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                // Track this email as imported to prevent duplicates within the same batch
                $existingEmails[$email] = true;

                $imported++;
            } catch (\Throwable $e) {
                $errors[] = [
                    'wp_id' => $wpUser['ID'] ?? '?',
                    'login' => $wpUser['user_login'] ?? '?',
                    'email' => $wpUser['user_email'] ?? '?',
                    'error' => $e->getMessage(),
                ];
                Log::warning("WP User Import error for ID {$wpUser['ID']}: {$e->getMessage()}");
            }
        }

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    /**
     * Purge all WP-imported users.
     * We identify them by checking users that have no videos and were created
     * with a specific marker, or we can use a date-based approach.
     * For safety, this uses a source tracking approach.
     */
    public function purgeImported(): int
    {
        // Purge users that have the wp_imported setting flag
        $users = User::where('settings->wp_imported', true)->get();
        $count = 0;

        foreach ($users as $user) {
            // Delete their channel
            Channel::where('user_id', $user->id)->delete();
            // Force delete the user
            $user->forceDelete();
            $count++;
        }

        return $count;
    }
}
