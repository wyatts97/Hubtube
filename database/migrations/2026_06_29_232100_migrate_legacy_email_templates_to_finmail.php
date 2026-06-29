<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = config('fin-mail.table_names.templates') ?? 'email_templates';

        // If the table already uses the FinMail schema (no 'slug' column), nothing to migrate.
        if (Schema::hasTable($table) && !Schema::hasColumn($table, 'slug')) {
            return;
        }

        // Capture legacy data before rebuilding the table.
        $legacy = [];
        if (Schema::hasTable($table)) {
            $legacy = DB::table($table)->get()->map(fn ($row) => (array) $row)->all();
        }

        Schema::disableForeignKeyConstraints();

        // Rebuild with the FinMail schema. Foreign key constraints are temporarily disabled
        // so dependent tables (versions, sent_emails) do not block the drop.
        Schema::dropIfExists($table);
        Schema::create($table, function (Blueprint $table) {
            $table->id();
            $table->string('key', 255)->unique();
            $table->json('name');
            $table->string('category', 100)->default('transactional')->index();
            $table->json('tags')->nullable();
            $table->json('subject');
            $table->json('preheader')->nullable();
            $table->json('body');
            $table->string('view_path', 255)->nullable();
            $table->json('from')->nullable();
            $table->json('reply_to')->nullable();
            $table->foreignId('email_theme_id')->nullable()->constrained(
                config('fin-mail.table_names.themes') ?? 'email_themes'
            )->nullOnDelete();
            $table->json('token_schema')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_locked')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        foreach ($legacy as $row) {
            $now = now();
            $payload = [
                'id' => $row['id'] ?? null,
                'key' => $row['slug'],
                'name' => json_encode(['en' => $row['name'] ?? $row['slug']]),
                'category' => 'migrated',
                'subject' => json_encode(['en' => $this->convertTokens($row['subject'] ?? '')]),
                'preheader' => null,
                'body' => json_encode(['en' => $this->convertTokens($row['body_html'] ?? '')]),
                'view_path' => null,
                'from' => null,
                'reply_to' => null,
                'email_theme_id' => null,
                'token_schema' => null,
                'is_active' => (bool) ($row['is_active'] ?? true),
                'is_locked' => false,
                'created_at' => $row['created_at'] ?? $now,
                'updated_at' => $row['updated_at'] ?? $now,
                'deleted_at' => null,
            ];

            if (empty($payload['id'])) {
                unset($payload['id']);
            }

            DB::table($table)->insert($payload);
        }

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        // No safe rollback that preserves migrated data; leave FinMail schema in place.
    }

    protected function convertTokens(string $text): string
    {
        // Convert legacy {{key}} tokens to FinMail's {{ key }} format.
        return (string) preg_replace('/\{\{\s*([^}]+?)\s*\}\}/', '{{ $1 }}', $text);
    }
};
