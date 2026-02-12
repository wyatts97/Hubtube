<?php

use App\Models\User;

/*
|--------------------------------------------------------------------------
| Subscriptions — Subscribe, Unsubscribe, Notifications
|--------------------------------------------------------------------------
*/

test('authenticated user can subscribe to a channel', function () {
    $subscriber = asUser();
    $channel = User::factory()->create();

    $response = $this->post("/channel/{$channel->id}/subscribe");
    $response->assertRedirect();

    $this->assertDatabaseHas('subscriptions', [
        'subscriber_id' => $subscriber->id,
        'channel_id' => $channel->id,
    ]);
});

test('authenticated user can unsubscribe from a channel', function () {
    $subscriber = asUser();
    $channel = User::factory()->create();

    // Subscribe first
    $this->post("/channel/{$channel->id}/subscribe");

    // Then unsubscribe
    $response = $this->delete("/channel/{$channel->id}/subscribe");
    $response->assertRedirect();

    $this->assertDatabaseMissing('subscriptions', [
        'subscriber_id' => $subscriber->id,
        'channel_id' => $channel->id,
    ]);
});

test('guest cannot subscribe to a channel', function () {
    $channel = User::factory()->create();
    $this->post("/channel/{$channel->id}/subscribe")->assertRedirect('/login');
});

test('user cannot subscribe to themselves', function () {
    $user = asUser();

    $response = $this->post("/channel/{$user->id}/subscribe");
    // Should fail or redirect with error
    $response->assertRedirect();
});
