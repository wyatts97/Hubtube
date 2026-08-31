<?php

namespace App\Observers;

use App\Models\Channel;
use App\Services\AltTextService;

class ChannelObserver
{
    public function __construct(private readonly AltTextService $altText) {}

    public function saving(Channel $channel): void
    {
        if (filled($channel->banner_alt_text) || blank($channel->name)) {
            return;
        }

        $channel->banner_alt_text = $this->altText->forChannelBanner($channel);
    }
}
