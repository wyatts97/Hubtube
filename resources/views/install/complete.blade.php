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
        <strong>Next steps:</strong> Start background services for full functionality. All other settings are in the Admin Panel.
    </div>

    <div style="margin-top: 1rem; padding: 0.75rem 1rem; background: #3f1d1d; border: 1px solid #7f1d1d; border-radius: 0.5rem; font-size: 0.8rem; color: #fca5a5;">
        <strong>Security notice:</strong> You should now delete or restrict access to the <code>/install</code> directory/routes on your server.
    </div>

    @if(isset($environment))
        <div style="margin-top: 1rem; padding: 1rem; background: #0a0a0a; border-radius: 0.5rem; font-size: 0.8rem;">
            <div style="color: #737373; margin-bottom: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em;">
                Background Services (SSH)
            </div>
            <div style="color: #a3a3a3; margin-bottom: 0.5rem;">
                HubTube needs <strong style="color: #fff;">Horizon</strong> (queue worker) and <strong style="color: #fff;">Reverb</strong> (WebSockets) running. Install Supervisor:
            </div>
            <pre style="background: #171717; padding: 0.75rem; border-radius: 0.375rem; overflow-x: auto; color: #4ade80; font-size: 0.75rem; line-height: 1.6; margin-bottom: 0.5rem;">sudo apt-get install -y supervisor

# Create /etc/supervisor/conf.d/hubtube-horizon.conf
# Create /etc/supervisor/conf.d/hubtube-reverb.conf
# See README.md for full config

sudo supervisorctl reread
sudo supervisorctl update</pre>
            @if($environment['panel'] !== 'none')
                <div style="color: #737373; margin-top: 0.5rem;">
                    Detected: <strong style="color: #fbbf24;">{{ ucfirst($environment['panel']) }}</strong>
                    @if($environment['open_basedir'])
                        — open_basedir is active, ensure it includes the full project directory.
                    @endif
                </div>
            @endif
        </div>
    @endif

    <div class="complete-links" style="margin-top: 1.5rem;">
        <a href="{{ url('/') }}" class="btn btn-secondary">Visit Site →</a>
        <a href="{{ url('/admin') }}" class="btn btn-primary">Admin Panel →</a>
    </div>
@endsection
