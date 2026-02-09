<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Install — {{ config('app.name', 'HubTube') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: #0a0a0a;
            color: #e5e5e5;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }
        .installer {
            width: 100%;
            max-width: 640px;
        }
        .logo {
            text-align: center;
            margin-bottom: 2rem;
        }
        .logo h1 {
            font-size: 2rem;
            font-weight: 700;
            color: #fff;
        }
        .logo h1 span { color: #ef4444; }
        .logo p {
            color: #737373;
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }

        /* Steps indicator */
        .steps {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-bottom: 2rem;
        }
        .step-dot {
            width: 2.5rem;
            height: 4px;
            border-radius: 2px;
            background: #262626;
            transition: background 0.3s;
        }
        .step-dot.active { background: #ef4444; }
        .step-dot.done { background: #22c55e; }

        /* Card */
        .card {
            background: #171717;
            border: 1px solid #262626;
            border-radius: 1rem;
            padding: 2rem;
        }
        .card-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #fff;
            margin-bottom: 0.25rem;
        }
        .card-desc {
            font-size: 0.875rem;
            color: #737373;
            margin-bottom: 1.5rem;
        }

        /* Form elements */
        label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            color: #a3a3a3;
            margin-bottom: 0.375rem;
        }
        input[type="text"], input[type="email"], input[type="password"],
        input[type="number"], input[type="url"], select {
            width: 100%;
            padding: 0.625rem 0.875rem;
            background: #0a0a0a;
            border: 1px solid #333;
            border-radius: 0.5rem;
            color: #fff;
            font-size: 0.875rem;
            font-family: inherit;
            outline: none;
            transition: border-color 0.2s;
        }
        input:focus, select:focus {
            border-color: #ef4444;
        }
        .form-group { margin-bottom: 1rem; }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        .form-hint {
            font-size: 0.75rem;
            color: #525252;
            margin-top: 0.25rem;
        }
        .form-error {
            font-size: 0.75rem;
            color: #ef4444;
            margin-top: 0.25rem;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.625rem 1.5rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
            text-decoration: none;
        }
        .btn-primary {
            background: #ef4444;
            color: #fff;
        }
        .btn-primary:hover { background: #dc2626; }
        .btn-primary:disabled {
            background: #7f1d1d;
            color: #fca5a5;
            cursor: not-allowed;
        }
        .btn-secondary {
            background: #262626;
            color: #a3a3a3;
        }
        .btn-secondary:hover { background: #333; color: #fff; }
        .btn-group {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1.5rem;
        }

        /* Check items */
        .check-list { list-style: none; }
        .check-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.625rem 0;
            border-bottom: 1px solid #1f1f1f;
            font-size: 0.875rem;
        }
        .check-item:last-child { border-bottom: none; }
        .check-icon {
            width: 1.25rem;
            height: 1.25rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 700;
            flex-shrink: 0;
        }
        .check-ok { background: #14532d; color: #4ade80; }
        .check-fail { background: #7f1d1d; color: #fca5a5; }
        .check-warn { background: #713f12; color: #fbbf24; }
        .check-label { flex: 1; color: #d4d4d4; }
        .check-value { color: #737373; font-size: 0.75rem; }

        /* Section divider */
        .section-title {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #525252;
            margin: 1rem 0 0.5rem;
        }

        /* Finalize steps */
        .finalize-step {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem;
            border-radius: 0.5rem;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
        }
        .finalize-step.success { background: #052e16; color: #4ade80; }
        .finalize-step.error { background: #450a0a; color: #fca5a5; }
        .finalize-step.warning { background: #422006; color: #fbbf24; }
        .finalize-step .step-msg {
            font-size: 0.75rem;
            opacity: 0.7;
            margin-top: 0.125rem;
        }

        /* Complete page */
        .complete-icon {
            width: 4rem;
            height: 4rem;
            background: #14532d;
            color: #4ade80;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin: 0 auto 1rem;
        }
        .complete-text {
            text-align: center;
            margin-bottom: 1.5rem;
        }
        .complete-text h2 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #fff;
            margin-bottom: 0.5rem;
        }
        .complete-links {
            display: flex;
            gap: 1rem;
            justify-content: center;
        }

        /* Alert */
        .alert {
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            margin-bottom: 1rem;
        }
        .alert-error {
            background: #450a0a;
            color: #fca5a5;
            border: 1px solid #7f1d1d;
        }
    </style>
</head>
<body>
    <div class="installer">
        <div class="logo">
            <h1>Hub<span>Tube</span></h1>
            <p>Installation Wizard</p>
        </div>

        @hasSection('steps')
            <div class="steps">
                @yield('steps')
            </div>
        @endif

        <div class="card">
            @yield('content')
        </div>
    </div>
</body>
</html>
