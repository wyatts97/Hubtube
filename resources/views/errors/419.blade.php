@php
    $siteName = \App\Models\Setting::get('site_title', 'HubTube');
    $siteLogo = \App\Models\Setting::get('site_logo', '');
    if ($siteLogo) {
        if (str_starts_with($siteLogo, 'http://') || str_starts_with($siteLogo, 'https://') || str_starts_with($siteLogo, '/')) {
            $logoUrl = $siteLogo;
        } else {
            $logoUrl = '/storage/' . $siteLogo;
        }
    } else {
        $logoUrl = '/icons/icon-192x192.png';
    }
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>419 — Session Expired</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #0a0a0a;
            color: #fff;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .header {
            padding: 1rem 2rem;
            border-bottom: 1px solid #1f1f1f;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .logo {
            height: 2rem;
            width: auto;
        }
        .site-name {
            font-size: 1.25rem;
            font-weight: 700;
            color: #fff;
        }
        .container {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 2rem;
        }
        .content {
            max-width: 480px;
        }
        .icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 1.5rem;
            background: linear-gradient(135deg, #ef4444, #f97316);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .icon svg {
            width: 40px;
            height: 40px;
            color: #fff;
        }
        .code {
            font-size: 4rem;
            font-weight: 800;
            line-height: 1;
            background: linear-gradient(135deg, #ef4444, #f97316);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
        }
        .title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #e5e5e5;
        }
        .desc {
            color: #a3a3a3;
            font-size: 0.95rem;
            line-height: 1.6;
            margin-bottom: 2rem;
        }
        .buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }
        .btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            text-decoration: none;
            border-radius: 0.5rem;
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
        }
        .btn-primary {
            background: #ef4444;
            color: #fff;
        }
        .btn-primary:hover { background: #dc2626; }
        .btn-secondary {
            background: #262626;
            color: #e5e5e5;
        }
        .btn-secondary:hover { background: #404040; }
    </style>
</head>
<body>
    <div class="header">
        <img src="{{ $logoUrl }}" alt="{{ $siteName }}" class="logo">
        <span class="site-name">{{ $siteName }}</span>
    </div>
    <div class="container">
        <div class="content">
            <div class="icon">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                </svg>
            </div>
            <div class="code">419</div>
            <h1 class="title">Session Expired</h1>
            <p class="desc">Your session has expired. Please refresh the page.</p>
            <div class="buttons">
                <a href="/" class="btn btn-primary">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1rem; height: 1rem; display: inline-block; vertical-align: middle; margin-right: 0.25rem;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                    </svg>
                    Go Home
                </a>
                <button onclick="window.location.reload()" class="btn btn-secondary">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1rem; height: 1rem; display: inline-block; vertical-align: middle; margin-right: 0.25rem;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                    </svg>
                    Refresh Page
                </button>
            </div>
        </div>
    </div>
</body>
</html>
