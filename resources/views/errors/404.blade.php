<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 — Page Not Found</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #0a0a0a;
            color: #fff;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container {
            text-align: center;
            padding: 2rem;
            max-width: 480px;
        }
        .code {
            font-size: 7rem;
            font-weight: 800;
            line-height: 1;
            background: linear-gradient(135deg, #ef4444, #f97316);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .title {
            font-size: 1.5rem;
            font-weight: 600;
            margin: 1rem 0 0.5rem;
            color: #e5e5e5;
        }
        .desc {
            color: #a3a3a3;
            font-size: 0.95rem;
            line-height: 1.6;
            margin-bottom: 2rem;
        }
        .btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            background: #ef4444;
            color: #fff;
            text-decoration: none;
            border-radius: 0.5rem;
            font-weight: 500;
            font-size: 0.9rem;
            transition: background 0.2s;
        }
        .btn:hover { background: #dc2626; }
    </style>
</head>
<body>
    <div class="container">
        <div class="code">404</div>
        <h1 class="title">Page Not Found</h1>
        <p class="desc">The page you're looking for doesn't exist or has been moved.</p>
        <a href="/" class="btn">Back to Home</a>
    </div>
</body>
</html>
