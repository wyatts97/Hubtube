@extends('install.layout')

@section('steps')
    <div class="step-dot done"></div>
    <div class="step-dot done"></div>
    <div class="step-dot done"></div>
    <div class="step-dot done"></div>
    <div class="step-dot done"></div>
@endsection

@section('content')
    <div class="complete-icon">✓</div>
    <div class="complete-text">
        <h2>Installation Complete!</h2>
        <p style="color: #737373;">Your site is ready to use. You can now log in with your admin account.</p>
    </div>

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
    @endif

    <div style="margin-top: 1.5rem; padding: 1rem; background: #0a0a0a; border-radius: 0.5rem; font-size: 0.875rem;">
        <div style="color: #737373; margin-bottom: 0.5rem;">Admin Credentials</div>
        <div style="display: flex; justify-content: space-between; margin-bottom: 0.25rem;">
            <span style="color: #a3a3a3;">Username</span>
            <span style="color: #fff;">{{ $adminData['username'] }}</span>
        </div>
        <div style="display: flex; justify-content: space-between;">
            <span style="color: #a3a3a3;">Email</span>
            <span style="color: #fff;">{{ $adminData['email'] }}</span>
        </div>
    </div>

    <div style="margin-top: 1rem; padding: 0.75rem 1rem; background: #422006; border: 1px solid #713f12; border-radius: 0.5rem; font-size: 0.8rem; color: #fbbf24;">
        <strong>Next steps:</strong> Build frontend assets with <code style="background: #0a0a0a; padding: 0.125rem 0.375rem; border-radius: 0.25rem;">npm install && npm run build</code>, then configure Redis, Reverb, and Horizon for full functionality. All other settings are in the Admin Panel.
    </div>

    <div class="complete-links" style="margin-top: 1.5rem;">
        <a href="{{ url('/') }}" class="btn btn-secondary">Visit Site →</a>
        <a href="{{ url('/admin') }}" class="btn btn-primary">Admin Panel →</a>
    </div>
@endsection
