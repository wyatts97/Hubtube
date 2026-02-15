<?php

namespace App\Services;

use Abraham\TwitterOAuth\TwitterOAuth;
use App\Models\Setting;
use App\Models\Video;
use App\Models\VideoTweet;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TwitterService
{
    protected ?TwitterOAuth $connection = null;

    public function getConnection(): TwitterOAuth
    {
        if ($this->connection) {
            return $this->connection;
        }

        $consumerKey = Setting::getDecrypted('twitter_api_consumer_key', '');
        $consumerSecret = Setting::getDecrypted('twitter_api_consumer_secret', '');
        $accessToken = Setting::getDecrypted('twitter_api_access_token', '');
        $accessTokenSecret = Setting::getDecrypted('twitter_api_access_token_secret', '');

        if (empty($consumerKey) || empty($consumerSecret) || empty($accessToken) || empty($accessTokenSecret)) {
            throw new \RuntimeException('Twitter API credentials are not configured. Go to Admin → Social Networks → Twitter Auto-Post.');
        }

        $this->connection = new TwitterOAuth(
            $consumerKey,
            $consumerSecret,
            $accessToken,
            $accessTokenSecret
        );

        $this->connection->setApiVersion('2');

        return $this->connection;
    }

    public function isConfigured(): bool
    {
        try {
            $consumerKey = Setting::getDecrypted('twitter_api_consumer_key', '');
            $consumerSecret = Setting::getDecrypted('twitter_api_consumer_secret', '');
            $accessToken = Setting::getDecrypted('twitter_api_access_token', '');
            $accessTokenSecret = Setting::getDecrypted('twitter_api_access_token_secret', '');

            return !empty($consumerKey) && !empty($consumerSecret) && !empty($accessToken) && !empty($accessTokenSecret);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function tweetNewVideo(Video $video): ?VideoTweet
    {
        if (!Setting::get('twitter_auto_tweet_new_enabled', false)) {
            return null;
        }

        if (!$this->isConfigured()) {
            Log::warning('TwitterService: Auto-tweet skipped — API credentials not configured.');
            return null;
        }

        // Prevent duplicate tweets for the same video
        $existing = VideoTweet::where('video_id', $video->id)
            ->where('tweet_type', 'new')
            ->exists();

        if ($existing) {
            Log::info("TwitterService: Skipping duplicate new-video tweet for video #{$video->id}");
            return null;
        }

        $text = $this->composeTweet($video);

        return $this->postTweet($video, $text, 'new');
    }

    public function tweetOlderVideo(Video $video): ?VideoTweet
    {
        if (!$this->isConfigured()) {
            Log::warning('TwitterService: Scheduled tweet skipped — API credentials not configured.');
            return null;
        }

        $text = $this->composeTweet($video);

        return $this->postTweet($video, $text, 'scheduled');
    }

    public function composeTweet(Video $video): string
    {
        $template = Setting::get('twitter_tweet_template', '{title} — Watch now: {url} #{category}');
        $hashtags = Setting::get('twitter_hashtags', '');

        $url = url('/' . $video->slug);
        $category = $video->category?->name ?? '';
        $channel = $video->user?->username ?? '';

        $text = str_replace(
            ['{title}', '{url}', '{category}', '{channel}'],
            [$video->title, $url, $category, $channel],
            $template
        );

        // Append additional hashtags
        if (!empty($hashtags)) {
            $tags = collect(explode(',', $hashtags))
                ->map(fn ($tag) => '#' . trim($tag))
                ->filter()
                ->implode(' ');

            if (!empty($tags)) {
                $text .= ' ' . $tags;
            }
        }

        // Twitter counts URLs as 23 chars (t.co)
        // Calculate effective length: replace URL with 23-char placeholder
        $effectiveLength = Str::length(str_replace($url, str_repeat('x', 23), $text));

        if ($effectiveLength > 280) {
            // Truncate title to fit
            $overhead = $effectiveLength - 280;
            $currentTitle = $video->title;
            $shortenedTitle = Str::limit($currentTitle, max(10, Str::length($currentTitle) - $overhead - 3), '…');

            $text = str_replace($video->title, $shortenedTitle, $text);
        }

        return $text;
    }

    protected function postTweet(Video $video, string $text, string $type): ?VideoTweet
    {
        try {
            $connection = $this->getConnection();

            $response = $connection->post('tweets', ['text' => $text], true);

            if ($connection->getLastHttpCode() === 201 || $connection->getLastHttpCode() === 200) {
                $tweetId = $response->data->id ?? null;
                $tweetUrl = $tweetId ? "https://x.com/i/status/{$tweetId}" : null;

                $videoTweet = VideoTweet::create([
                    'video_id' => $video->id,
                    'tweet_id' => $tweetId,
                    'tweet_type' => $type,
                    'tweeted_at' => now(),
                    'tweet_url' => $tweetUrl,
                ]);

                Log::info("TwitterService: Tweet posted for video #{$video->id}", [
                    'tweet_id' => $tweetId,
                    'type' => $type,
                ]);

                return $videoTweet;
            }

            Log::error('TwitterService: Failed to post tweet', [
                'http_code' => $connection->getLastHttpCode(),
                'response' => json_encode($response),
                'video_id' => $video->id,
            ]);

            return null;
        } catch (\Throwable $e) {
            Log::error('TwitterService: Exception posting tweet', [
                'error' => $e->getMessage(),
                'video_id' => $video->id,
            ]);

            return null;
        }
    }

    public function sendTestTweet(): bool
    {
        $connection = $this->getConnection();

        $text = '🎬 Test tweet from ' . config('app.name', 'HubTube') . ' — Auto-posting is configured and working! ' . now()->format('Y-m-d H:i:s');

        $response = $connection->post('tweets', ['text' => $text], true);

        if ($connection->getLastHttpCode() === 201 || $connection->getLastHttpCode() === 200) {
            Log::info('TwitterService: Test tweet sent successfully');
            return true;
        }

        Log::error('TwitterService: Test tweet failed', [
            'http_code' => $connection->getLastHttpCode(),
            'response' => json_encode($response),
        ]);

        throw new \RuntimeException(
            'Twitter API returned HTTP ' . $connection->getLastHttpCode() . '. Check your API credentials and permissions.'
        );
    }
}
