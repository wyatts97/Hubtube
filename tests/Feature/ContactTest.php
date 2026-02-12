<?php

/*
|--------------------------------------------------------------------------
| Contact Form — Submission, Validation, Rate Limiting
|--------------------------------------------------------------------------
*/

test('contact page loads', function () {
    $this->get('/contact')->assertStatus(200);
});

test('contact form requires valid data', function () {
    $response = $this->post('/contact', []);
    $response->assertSessionHasErrors();
});

test('contact form submits with valid data', function () {
    $response = $this->post('/contact', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'subject' => 'Test Subject',
        'message' => 'This is a test message for the contact form.',
    ]);

    $response->assertRedirect();
});
