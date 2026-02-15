<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Models\Video;
use App\Models\VideoTweet;
use App\Services\TwitterService;
use Illuminate\Console\Command;

class TweetOlderVideoCommand extends Command
{
    protected $signature = 'tweets:older-video';

    protected $description = 'Tweet a random older video for ongoing engagement';

    public function handle(TwitterService $service): int
    {
        if (!Setting::get('twitter_auto_tweet_scheduled_enabled', false)) {
            $this->info('Scheduled older video tweets are disabled.');
            return self::SUCCESS;
        }

        if (!$service->isConfigured()) {
            $this->warn('Twitter API credentials are not configured.');
            return self::FAILURE;
        }

        // Check if enough time has passed since the last scheduled tweet
        $intervalHours = (int) Setting::get('twitter_tweet_interval_hours', 4);
        $lastScheduledTweet = VideoTweet::where('tweet_type', 'scheduled')
            ->orderByDesc('tweeted_at')
            ->first();

        if ($lastScheduledTweet && $lastScheduledTweet->tweeted_at->diffInHours(now()) < $intervalHours) {
            $this->info("Last scheduled tweet was less than {$intervalHours} hours ago. Skipping.");
            return self::SUCCESS;
        }

        $minAgeDays = (int) Setting::get('twitter_min_video_age_days', 7);
        $noRetweetDays = (int) Setting::get('twitter_no_retweet_within_days', 30);

        // Find a random published video that:
        // 1. Is older than $minAgeDays
        // 2. Has not been tweeted within $noRetweetDays
        $video = Video::where('status', 'processed')
            ->where('created_at', '<', now()->subDays($minAgeDays))
            ->whereDoesntHave('tweets', function ($query) use ($noRetweetDays) {
                $query->where('tweeted_at', '>', now()->subDays($noRetweetDays));
            })
            ->inRandomOrder()
            ->first();

        if (!$video) {
            $this->info('No eligible videos found for scheduled tweet.');
            return self::SUCCESS;
        }

        $tweet = $service->tweetOlderVideo($video);

        if ($tweet) {
            $this->info("Tweeted video: {$video->title} (Tweet ID: {$tweet->tweet_id})");
            return self::SUCCESS;
        }

        $this->error("Failed to tweet video: {$video->title}");
        return self::FAILURE;
    }
}
