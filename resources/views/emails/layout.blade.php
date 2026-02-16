<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject ?? config('app.name') }}</title>
    <style>
        body { margin: 0; padding: 0; background-color: #f3f4f6; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; }
        .email-wrapper { width: 100%; background-color: #f3f4f6; padding: 32px 0; }
        .email-container { max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .email-header { background-color: #4f46e5; padding: 24px 32px; text-align: center; }
        .email-header a { color: #ffffff; font-size: 22px; font-weight: 700; text-decoration: none; }
        .email-body { padding: 32px; color: #374151; font-size: 15px; line-height: 1.6; }
        .email-body p { margin: 0 0 16px; }
        .email-body a { color: #4f46e5; }
        .email-body blockquote { border-left: 4px solid #e5e7eb; padding-left: 16px; color: #6b7280; margin: 16px 0; }
        .email-footer { padding: 24px 32px; text-align: center; font-size: 12px; color: #9ca3af; border-top: 1px solid #f3f4f6; }
        .email-footer a { color: #9ca3af; text-decoration: underline; }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <div class="email-container">
            <div class="email-header">
                <a href="{{ config('app.url') }}">{{ config('app.name') }}</a>
            </div>
            <div class="email-body">
                {!! $body !!}
            </div>
            <div class="email-footer">
                <p>&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
                <p><a href="{{ config('app.url') }}">{{ config('app.url') }}</a></p>
            </div>
        </div>
    </div>
</body>
</html>
