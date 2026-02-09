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

        <div class="btn-group">
            <a href="{{ route('install.database') }}" class="btn btn-secondary">← Back</a>
            <button type="submit" class="btn btn-primary">Continue →</button>
        </div>
    </form>
@endsection
