<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Under Maintenance</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #0f0f0f;
            color: #e5e5e5;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            padding: 1.5rem;
        }

        .container {
            text-align: center;
            max-width: 520px;
            width: 100%;
        }

        .icon-wrap {
            width: 96px;
            height: 96px;
            border-radius: 50%;
            background: rgba(234, 179, 8, 0.15);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2rem;
        }

        .icon-wrap svg {
            width: 48px;
            height: 48px;
            color: #eab308;
        }

        h1 {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            color: #fff;
        }

        .message {
            font-size: 1.125rem;
            line-height: 1.7;
            color: #a3a3a3;
            margin-bottom: 2rem;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1.25rem;
            border-radius: 9999px;
            background: rgba(234, 179, 8, 0.1);
            border: 1px solid rgba(234, 179, 8, 0.25);
            color: #eab308;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .pulse {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #eab308;
            animation: pulse 2s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(0.8); }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon-wrap">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 0 0 4.486-6.336l-3.276 3.277a3.004 3.004 0 0 1-2.25-2.25l3.276-3.276a4.5 4.5 0 0 0-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085" />
            </svg>
        </div>

        <h1>Under Maintenance</h1>

        <p class="message">{{ $message }}</p>

        <div class="status-badge">
            <span class="pulse"></span>
            We'll be back shortly
        </div>
    </div>
</body>
</html>
