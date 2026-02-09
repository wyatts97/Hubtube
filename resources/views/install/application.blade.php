@extends('install.layout')

@section('steps')
    <div class="step-dot done"></div>
    <div class="step-dot done"></div>
    <div class="step-dot active"></div>
    <div class="step-dot"></div>
    <div class="step-dot"></div>
@endsection

@section('content')
    <h2 class="card-title">Step 3: Application Settings</h2>
    <p class="card-desc">Configure your site name, URL, and timezone.</p>

    <form method="POST" action="{{ route('install.application.save') }}">
        @csrf

        <div class="form-group">
            <label for="app_name">Site Name</label>
            <input type="text" name="app_name" id="app_name" value="{{ old('app_name', $current['app_name']) }}" />
            @error('app_name') <p class="form-error">{{ $message }}</p> @enderror
        </div>

        <div class="form-group">
            <label for="app_url">Site URL</label>
            <input type="url" name="app_url" id="app_url" value="{{ old('app_url', $current['app_url']) }}" placeholder="https://yourdomain.com" />
            <p class="form-hint">Full URL including https:// — no trailing slash.</p>
            @error('app_url') <p class="form-error">{{ $message }}</p> @enderror
        </div>

        <div class="form-group">
            <label for="app_timezone">Timezone</label>
            <select name="app_timezone" id="app_timezone">
                @foreach($timezones as $tz)
                    <option value="{{ $tz }}" {{ old('app_timezone', $current['app_timezone']) === $tz ? 'selected' : '' }}>{{ $tz }}</option>
                @endforeach
            </select>
            @error('app_timezone') <p class="form-error">{{ $message }}</p> @enderror
        </div>

        <div class="section-title" style="margin-top: 1.5rem;">Email Configuration</div>
        <p class="form-hint" style="margin-bottom: 1rem;">Required for email verification and password resets. Use "log" to skip for now.</p>

        <div class="form-group">
            <label for="mail_mailer">Mail Driver</label>
            <select name="mail_mailer" id="mail_mailer" onchange="document.getElementById('smtp-fields').style.display = this.value === 'smtp' ? 'block' : 'none';">
                @foreach(['smtp' => 'SMTP', 'log' => 'Log (no emails sent)', 'sendmail' => 'Sendmail', 'ses' => 'Amazon SES', 'postmark' => 'Postmark', 'resend' => 'Resend'] as $val => $label)
                    <option value="{{ $val }}" {{ old('mail_mailer', $current['mail_mailer']) === $val ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
            @error('mail_mailer') <p class="form-error">{{ $message }}</p> @enderror
        </div>

        <div id="smtp-fields" style="{{ old('mail_mailer', $current['mail_mailer']) === 'smtp' ? '' : 'display:none;' }}">
            <div class="form-row">
                <div class="form-group">
                    <label for="mail_host">SMTP Host</label>
                    <input type="text" name="mail_host" id="mail_host" value="{{ old('mail_host', $current['mail_host']) }}" placeholder="smtp.gmail.com" />
                </div>
                <div class="form-group">
                    <label for="mail_port">SMTP Port</label>
                    <input type="number" name="mail_port" id="mail_port" value="{{ old('mail_port', $current['mail_port']) }}" placeholder="587" />
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="mail_username">SMTP Username</label>
                    <input type="text" name="mail_username" id="mail_username" value="{{ old('mail_username', $current['mail_username']) }}" />
                </div>
                <div class="form-group">
                    <label for="mail_password">SMTP Password</label>
                    <input type="password" name="mail_password" id="mail_password" value="" placeholder="Leave blank to keep current" />
                </div>
            </div>
            <div class="form-group">
                <label for="mail_encryption">Encryption</label>
                <select name="mail_encryption" id="mail_encryption">
                    <option value="tls" {{ old('mail_encryption', $current['mail_encryption']) === 'tls' ? 'selected' : '' }}>TLS</option>
                    <option value="ssl" {{ old('mail_encryption', $current['mail_encryption']) === 'ssl' ? 'selected' : '' }}>SSL</option>
                    <option value="null" {{ old('mail_encryption', $current['mail_encryption']) === 'null' ? 'selected' : '' }}>None</option>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label for="mail_from_address">From Address</label>
            <input type="email" name="mail_from_address" id="mail_from_address" value="{{ old('mail_from_address', $current['mail_from_address']) }}" placeholder="noreply@yourdomain.com" />
            <p class="form-hint">The email address that outgoing emails will be sent from.</p>
        </div>

        <div class="btn-group">
            <a href="{{ route('install.database') }}" class="btn btn-secondary">← Back</a>
            <button type="submit" class="btn btn-primary">Continue →</button>
        </div>
    </form>
@endsection
