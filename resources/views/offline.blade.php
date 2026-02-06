<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Offline - HubTube</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #0a0a0a; color: #fff; font-family: Inter, system-ui, sans-serif; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .container { text-align: center; padding: 2rem; }
        .icon { font-size: 4rem; margin-bottom: 1rem; }
        h1 { font-size: 1.5rem; margin-bottom: 0.5rem; }
        p { color: #a3a3a3; margin-bottom: 1.5rem; }
        .btn { display: inline-block; padding: 0.75rem 1.5rem; background: #ef4444; color: #fff; border-radius: 0.5rem; text-decoration: none; font-weight: 500; }
        .btn:hover { opacity: 0.9; }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">📡</div>
        <h1>You're Offline</h1>
        <p>Check your internet connection and try again.</p>
        <a href="/" class="btn" onclick="window.location.reload(); return false;">Retry</a>
    </div>
</body>
</html>
