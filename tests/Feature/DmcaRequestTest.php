<?php

use App\Models\DmcaRequest;
use App\Models\Video;

it('shows the dmca request form', function () {
    $this->get('/dmca-request')->assertOk();
});

it('accepts a valid dmca takedown request and links the matching video by slug', function () {
    $video = Video::factory()->create(['slug' => 'some-infringing-video']);

    $response = $this->post('/dmca-request', [
        'complainant_name' => 'Jane Rightsholder',
        'complainant_email' => 'jane@example.com',
        'complainant_company' => 'Acme Media',
        'copyrighted_work_description' => 'A short film titled "Example".',
        'infringing_urls' => url('/some-infringing-video'),
        'good_faith_statement' => '1',
        'accuracy_statement' => '1',
        'signature' => 'Jane Rightsholder',
    ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    $this->assertDatabaseHas('dmca_requests', [
        'complainant_email' => 'jane@example.com',
        'video_id' => $video->id,
        'status' => DmcaRequest::STATUS_PENDING,
    ]);
});

it('rejects a submission missing the required legal statements', function () {
    $response = $this->post('/dmca-request', [
        'complainant_name' => 'Jane Rightsholder',
        'complainant_email' => 'jane@example.com',
        'copyrighted_work_description' => 'A short film titled "Example".',
        'infringing_urls' => 'https://example.com/some-video',
        'signature' => 'Jane Rightsholder',
        // good_faith_statement / accuracy_statement omitted
    ]);

    $response->assertSessionHasErrors(['good_faith_statement', 'accuracy_statement']);
    $this->assertDatabaseCount('dmca_requests', 0);
});
