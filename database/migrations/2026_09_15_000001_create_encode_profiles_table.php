<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Encoding profiles: the resolution ladder videos are transcoded to.
 *
 * Replaces the fixed 240p–1080p list hard-coded in ProcessVideoJob and the
 * `enabled_resolutions` setting that picked from it. Rows are seeded here
 * rather than in a seeder so existing installs get them on migrate, with
 * `is_active` carried over from whatever that setting held.
 *
 * Only H.264 is produced for now: it is the one codec every browser plays
 * from both progressive MP4 and the MPEG-TS HLS the player uses. The column
 * exists so another codec can be added without a schema change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('encode_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 32)->unique();
            $table->string('codec', 16)->default('h264');
            $table->unsignedSmallInteger('width');
            $table->unsignedSmallInteger('height');
            $table->string('video_bitrate', 16);
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });

        $enabled = ['360p', '480p', '720p'];

        $stored = DB::table('settings')->where('key', 'enabled_resolutions')->value('value');
        if (is_string($stored)) {
            $decoded = json_decode($stored, true);
            if (is_array($decoded)) {
                $enabled = $decoded;
            }
        }

        $now = now();
        $ladder = [
            ['144p', 256, 144, '250k'],
            ['240p', 426, 240, '400k'],
            ['360p', 640, 360, '800k'],
            ['480p', 854, 480, '1400k'],
            ['720p', 1280, 720, '2800k'],
            ['1080p', 1920, 1080, '5000k'],
            ['1440p', 2560, 1440, '8000k'],
            ['2160p', 3840, 2160, '16000k'],
        ];

        DB::table('encode_profiles')->insert(array_map(fn (array $row) => [
            'name' => $row[0],
            'codec' => 'h264',
            'width' => $row[1],
            'height' => $row[2],
            'video_bitrate' => $row[3],
            'is_active' => in_array($row[0], $enabled, true),
            'created_at' => $now,
            'updated_at' => $now,
        ], $ladder));
    }

    public function down(): void
    {
        Schema::dropIfExists('encode_profiles');
    }
};
