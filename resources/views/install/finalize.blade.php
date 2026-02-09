@extends('install.layout')

@section('steps')
    <div class="step-dot done"></div>
    <div class="step-dot done"></div>
    <div class="step-dot done"></div>
    <div class="step-dot done"></div>
    <div class="step-dot active"></div>
@endsection

@section('content')
    <h2 class="card-title">Step 5: Finalize Installation</h2>
    <p class="card-desc">Ready to set up your database tables, seed default data, and create your admin account.</p>

    @if(isset($steps))
        @foreach($steps as $step)
            <div class="finalize-step {{ $step['status'] }}">
                <span>
                    @if($step['status'] === 'success') ✓
                    @elseif($step['status'] === 'error') ✗
                    @else !
                    @endif
                </span>
                <div>
                    <div>{{ $step['label'] }}</div>
                    @if(isset($step['message']))
                        <div class="step-msg">{{ $step['message'] }}</div>
                    @endif
                </div>
            </div>
        @endforeach

        @if(isset($failed) && $failed)
            <div class="alert alert-error" style="margin-top: 1rem;">
                Installation failed. Fix the issue above and try again.
            </div>
            <div class="btn-group">
                <a href="{{ route('install.admin') }}" class="btn btn-secondary">← Back</a>
                <form method="POST" action="{{ route('install.finalize.execute') }}">
                    @csrf
                    <button type="submit" class="btn btn-primary">Retry →</button>
                </form>
            </div>
        @endif
    @else
        <div style="margin-bottom: 1rem;">
            <div class="section-title">What will happen</div>
            <ul class="check-list">
                <li class="check-item">
                    <span class="check-icon check-ok">1</span>
                    <span class="check-label">Run database migrations (create all tables)</span>
                </li>
                <li class="check-item">
                    <span class="check-icon check-ok">2</span>
                    <span class="check-label">Seed categories, gifts, and default settings</span>
                </li>
                <li class="check-item">
                    <span class="check-icon check-ok">3</span>
                    <span class="check-label">Create admin account: <strong>{{ $adminData['username'] }}</strong> ({{ $adminData['email'] }})</span>
                </li>
                <li class="check-item">
                    <span class="check-icon check-ok">4</span>
                    <span class="check-label">Create storage symlink</span>
                </li>
                <li class="check-item">
                    <span class="check-icon check-ok">5</span>
                    <span class="check-label">Clear application caches</span>
                </li>
            </ul>
        </div>

        <div class="btn-group">
            <a href="{{ route('install.admin') }}" class="btn btn-secondary">← Back</a>
            <form method="POST" action="{{ route('install.finalize.execute') }}">
                @csrf
                <button type="submit" class="btn btn-primary">Install Now →</button>
            </form>
        </div>
    @endif
@endsection
