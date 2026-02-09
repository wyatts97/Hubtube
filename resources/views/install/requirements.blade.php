@extends('install.layout')

@section('steps')
    <div class="step-dot active"></div>
    <div class="step-dot"></div>
    <div class="step-dot"></div>
    <div class="step-dot"></div>
    <div class="step-dot"></div>
@endsection

@section('content')
    <h2 class="card-title">Step 1: Requirements Check</h2>
    <p class="card-desc">Verifying your server meets the minimum requirements.</p>

    <div class="section-title">PHP Version</div>
    <ul class="check-list">
        <li class="check-item">
            <span class="check-icon {{ $requirements['php_ok'] ? 'check-ok' : 'check-fail' }}">
                {{ $requirements['php_ok'] ? '✓' : '✗' }}
            </span>
            <span class="check-label">PHP {{ $requirements['php_version'] }}</span>
            <span class="check-value">≥ 8.2 required</span>
        </li>
    </ul>

    <div class="section-title">Required Extensions</div>
    <ul class="check-list">
        @foreach($requirements['extensions'] as $ext => $loaded)
            <li class="check-item">
                <span class="check-icon {{ $loaded ? 'check-ok' : 'check-fail' }}">
                    {{ $loaded ? '✓' : '✗' }}
                </span>
                <span class="check-label">{{ $ext }}</span>
            </li>
        @endforeach
    </ul>

    <div class="section-title">Directory Permissions</div>
    <ul class="check-list">
        @foreach($requirements['directories'] as $dir => $writable)
            <li class="check-item">
                <span class="check-icon {{ $writable ? 'check-ok' : 'check-fail' }}">
                    {{ $writable ? '✓' : '✗' }}
                </span>
                <span class="check-label">{{ $dir }}</span>
                <span class="check-value">{{ $writable ? 'Writable' : 'Not writable' }}</span>
            </li>
        @endforeach
    </ul>

    <div class="section-title">Environment File</div>
    <ul class="check-list">
        <li class="check-item">
            <span class="check-icon {{ $requirements['env_exists'] ? 'check-ok' : 'check-fail' }}">
                {{ $requirements['env_exists'] ? '✓' : '✗' }}
            </span>
            <span class="check-label">.env file</span>
            <span class="check-value">
                @if(!$requirements['env_exists'])
                    Missing — copy .env.example to .env
                @elseif($requirements['env_writable'])
                    Exists &amp; writable
                @else
                    Exists but not writable
                @endif
            </span>
        </li>
    </ul>

    <div class="section-title">Optional Tools</div>
    <ul class="check-list">
        <li class="check-item">
            <span class="check-icon {{ $requirements['ffmpeg_installed'] ? 'check-ok' : 'check-warn' }}">
                {{ $requirements['ffmpeg_installed'] ? '✓' : '!' }}
            </span>
            <span class="check-label">FFmpeg</span>
            <span class="check-value">{{ $requirements['ffmpeg_installed'] ? $requirements['ffmpeg_path'] : 'Not found (needed for video processing)' }}</span>
        </li>
        <li class="check-item">
            <span class="check-icon {{ $requirements['node_installed'] ? 'check-ok' : 'check-warn' }}">
                {{ $requirements['node_installed'] ? '✓' : '!' }}
            </span>
            <span class="check-label">Node.js</span>
            <span class="check-value">{{ $requirements['node_installed'] ? $requirements['node_version'] : 'Not found (needed for asset building)' }}</span>
        </li>
        <li class="check-item">
            <span class="check-icon {{ $requirements['composer_installed'] ? 'check-ok' : 'check-warn' }}">
                {{ $requirements['composer_installed'] ? '✓' : '!' }}
            </span>
            <span class="check-label">Composer</span>
            <span class="check-value">{{ $requirements['composer_installed'] ? 'Installed' : 'Not found' }}</span>
        </li>
        <li class="check-item">
            <span class="check-icon {{ $requirements['redis_available'] ? 'check-ok' : 'check-warn' }}">
                {{ $requirements['redis_available'] ? '✓' : '!' }}
            </span>
            <span class="check-label">Redis</span>
            <span class="check-value">{{ $requirements['redis_available'] ? 'Connected' : 'Not reachable (needed for queues, cache, Horizon)' }}</span>
        </li>
    </ul>

    <div class="btn-group">
        <div></div>
        @if($requirements['can_proceed'])
            <a href="{{ route('install.database') }}" class="btn btn-primary">Continue →</a>
        @else
            <button class="btn btn-primary" disabled>Fix issues above to continue</button>
        @endif
    </div>
@endsection
