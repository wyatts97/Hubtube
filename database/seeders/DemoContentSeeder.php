<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Setting;
use App\Models\User;
use App\Models\Video;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Mock content for looking at the frontend.
 *
 * NOT for production — it creates throwaway accounts with a known password and
 * fills the catalogue with generated placeholder art. It exists so the redesign
 * can be reviewed against a populated grid, where density, the rating bar and
 * the filter rail actually mean something; an empty install shows none of that.
 *
 * Thumbnails are rendered locally with GD rather than pulled from a placeholder
 * service, so this works with no network access and produces the same images
 * every run.
 */
class DemoContentSeeder extends Seeder
{
    private const THUMB_DIR = 'demo-thumbs';

    private const CATEGORIES = [
        'Amateur', 'Solo', 'Couples', 'Vintage', 'HD Exclusive', 'Trending Now',
    ];

    private const TITLE_WORDS = [
        ['Late Night', 'Golden Hour', 'Backstage', 'First Take', 'Slow Burn', 'After Hours',
         'Behind Closed Doors', 'Weekend', 'Unscripted', 'Close Up', 'Midnight', 'Off Camera'],
        ['Session', 'Cut', 'Compilation', 'Special', 'Feature', 'Short', 'Set', 'Reel',
         'Collection', 'Take', 'Edition', 'Scene'],
    ];

    private const TAGS = [
        'hd', '4k', 'amateur', 'solo', 'couple', 'vintage', 'verified', 'exclusive',
        'trending', 'long', 'short', 'pov', 'behind-the-scenes', 'uncut',
    ];

    public function run(): void
    {
        // Deterministic output so repeated runs produce an identical catalogue
        // and a visual diff between builds is meaningful.
        mt_srand(20260906);

        $this->seedSettings();
        $categories = $this->seedCategories();
        $users = $this->seedUsers();
        $this->seedVideos($categories, $users);

        $this->command?->info('Demo content ready — 60 videos, 6 categories, 6 channels.');
        $this->command?->info('Sign in as demo@example.com / password');
    }

    private function seedSettings(): void
    {
        $settings = [
            'site_title' => 'HubTube',
            'site_name' => 'HubTube',
            'theme_mode' => 'user',
            'video_grid_density' => 'dense',
            'mobile_video_grid' => '2',
            'age_verification_enabled' => '0',
        ];

        foreach ($settings as $key => $value) {
            Setting::set($key, $value, 'theme', 'string');
        }
    }

    /** @return list<Category> */
    private function seedCategories(): array
    {
        $categories = [];

        foreach (self::CATEGORIES as $index => $name) {
            $categories[] = Category::updateOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'description' => $name . ' videos',
                    'is_active' => true,
                    'sort_order' => $index,
                ]
            );
        }

        return $categories;
    }

    /** @return list<User> */
    private function seedUsers(): array
    {
        $names = ['nightowl', 'studio_ninety', 'reelworks', 'analog_dreams', 'lateshift', 'goldenhour'];
        $users = [];

        foreach ($names as $index => $username) {
            $users[] = User::updateOrCreate(
                ['email' => $index === 0 ? 'demo@example.com' : $username . '@example.com'],
                [
                    'username' => $username,
                    'first_name' => Str::headline(str_replace('_', ' ', $username)),
                    'password' => bcrypt('password'),
                    'email_verified_at' => now(),
                    'is_verified' => $index % 2 === 0,
                    'is_pro' => $index === 1,
                ]
            );
        }

        return $users;
    }

    /**
     * @param list<Category> $categories
     * @param list<User> $users
     */
    private function seedVideos(array $categories, array $users): void
    {
        $thumbs = $this->generateThumbnails(24);

        for ($i = 0; $i < 60; $i++) {
            $title = $this->title();
            $duration = $this->duration();

            // Spread the vote counts so the rating bar shows a real range, and
            // leave roughly one in six below the display threshold so the
            // "not enough votes" path is visible too.
            $hasEnoughVotes = $i % 6 !== 0;
            $likes = $hasEnoughVotes ? mt_rand(20, 4000) : mt_rand(0, 3);
            $dislikes = $hasEnoughVotes ? (int) round($likes * (mt_rand(2, 60) / 100)) : 0;

            Video::updateOrCreate(
                ['slug' => Str::slug($title) . '-' . $i],
                [
                    'user_id' => $users[$i % count($users)]->id,
                    'uuid' => (string) Str::uuid(),
                    'title' => $title,
                    'description' => 'Demo content generated for frontend review. Not a real video.',
                    'category_id' => $categories[$i % count($categories)]->id,
                    'external_thumbnail_url' => $thumbs[$i % count($thumbs)],
                    'duration' => $duration,
                    'views_count' => mt_rand(120, 2_400_000),
                    'likes_count' => $likes,
                    'dislikes_count' => $dislikes,
                    'comments_count' => mt_rand(0, 340),
                    'qualities_available' => $this->qualities(),
                    'tags' => $this->tags(),
                    'privacy' => 'public',
                    'status' => 'processed',
                    'is_approved' => true,
                    'is_featured' => $i < 5,
                    'is_embedded' => true,
                    'embed_url' => 'https://example.invalid/demo',
                    'published_at' => now()->subDays(mt_rand(0, 400))->subHours(mt_rand(0, 23)),
                ]
            );
        }
    }

    private function title(): string
    {
        return self::TITLE_WORDS[0][array_rand(self::TITLE_WORDS[0])]
            . ' ' . self::TITLE_WORDS[1][array_rand(self::TITLE_WORDS[1])]
            . ' ' . mt_rand(1, 99);
    }

    /** Weighted so all three duration filter buckets are well populated. */
    private function duration(): int
    {
        return match (mt_rand(1, 3)) {
            1 => mt_rand(45, 295),
            2 => mt_rand(300, 1195),
            default => mt_rand(1200, 5400),
        };
    }

    /** @return list<string> */
    private function qualities(): array
    {
        return match (mt_rand(1, 4)) {
            1 => ['480p'],
            2 => ['480p', '720p'],
            3 => ['480p', '720p', '1080p'],
            default => ['480p', '720p', '1080p', '2160p'],
        };
    }

    /** @return list<string> */
    private function tags(): array
    {
        $tags = self::TAGS;
        shuffle($tags);

        return array_slice($tags, 0, mt_rand(2, 5));
    }

    /**
     * Render placeholder thumbnails into public/ and return their URLs.
     *
     * @return list<string>
     */
    private function generateThumbnails(int $count): array
    {
        $dir = public_path(self::THUMB_DIR);

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (! function_exists('imagecreatetruecolor')) {
            $this->command?->warn('GD not available — demo videos will use the built-in placeholder.');

            return [''];
        }

        $urls = [];

        for ($i = 0; $i < $count; $i++) {
            $path = $dir . DIRECTORY_SEPARATOR . $i . '.jpg';
            $urls[] = '/' . self::THUMB_DIR . '/' . $i . '.jpg';

            if (file_exists($path)) {
                continue;
            }

            $this->renderThumbnail($path, $i, $count);
        }

        return $urls;
    }

    /**
     * A muted diagonal gradient with a couple of soft shapes. Deliberately dim
     * and low-contrast: bright synthetic swatches would flatter the light theme
     * in a way real thumbnails never do, and the point of the demo is to judge
     * how the design handles actual content.
     */
    private function renderThumbnail(string $path, int $index, int $count): void
    {
        $width = 640;
        $height = 360;

        $image = imagecreatetruecolor($width, $height);

        $hue = ($index / max(1, $count)) * 360.0;
        [$r1, $g1, $b1] = $this->hslToRgb($hue, 0.30, 0.22);
        [$r2, $g2, $b2] = $this->hslToRgb(fmod($hue + 40, 360), 0.35, 0.08);

        for ($y = 0; $y < $height; $y++) {
            $t = $y / $height;
            $color = imagecolorallocate(
                $image,
                (int) ($r1 + ($r2 - $r1) * $t),
                (int) ($g1 + ($g2 - $g1) * $t),
                (int) ($b1 + ($b2 - $b1) * $t)
            );
            imageline($image, 0, $y, $width, $y, $color);
        }

        [$hr, $hg, $hb] = $this->hslToRgb(fmod($hue + 180, 360), 0.40, 0.42);
        $highlight = imagecolorallocatealpha($image, $hr, $hg, $hb, 95);
        imagefilledellipse($image, (int) ($width * 0.32), (int) ($height * 0.42), 260, 260, $highlight);
        imagefilledellipse($image, (int) ($width * 0.72), (int) ($height * 0.66), 180, 180, $highlight);

        imagejpeg($image, $path, 82);
        imagedestroy($image);
    }

    /** @return array{0:int,1:int,2:int} */
    private function hslToRgb(float $h, float $s, float $l): array
    {
        $c = (1 - abs(2 * $l - 1)) * $s;
        $x = $c * (1 - abs(fmod($h / 60, 2) - 1));
        $m = $l - $c / 2;

        [$r, $g, $b] = match (true) {
            $h < 60 => [$c, $x, 0.0],
            $h < 120 => [$x, $c, 0.0],
            $h < 180 => [0.0, $c, $x],
            $h < 240 => [0.0, $x, $c],
            $h < 300 => [$x, 0.0, $c],
            default => [$c, 0.0, $x],
        };

        return [
            (int) round(($r + $m) * 255),
            (int) round(($g + $m) * 255),
            (int) round(($b + $m) * 255),
        ];
    }
}
