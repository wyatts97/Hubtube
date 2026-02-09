@extends('install.layout')

@section('steps')
    <div class="step-dot done"></div>
    <div class="step-dot done"></div>
    <div class="step-dot done"></div>
    <div class="step-dot active"></div>
    <div class="step-dot"></div>
@endsection

@section('content')
    <h2 class="card-title">Step 4: Admin Account</h2>
    <p class="card-desc">Create the first administrator account. You can add more admins later from the admin panel.</p>

    <form method="POST" action="{{ route('install.admin.save') }}">
        @csrf

        <div class="form-group">
            <label for="username">Username</label>
            <input type="text" name="username" id="username" value="{{ old('username') }}" placeholder="admin" />
            <p class="form-hint">Letters, numbers, and underscores only. 3–30 characters.</p>
            @error('username') <p class="form-error">{{ $message }}</p> @enderror
        </div>

        <div class="form-group">
            <label for="email">Email Address</label>
            <input type="email" name="email" id="email" value="{{ old('email') }}" placeholder="admin@yourdomain.com" />
            @error('email') <p class="form-error">{{ $message }}</p> @enderror
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" name="password" id="password" />
                @error('password') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <div class="form-group">
                <label for="password_confirmation">Confirm Password</label>
                <input type="password" name="password_confirmation" id="password_confirmation" />
            </div>
        </div>
        <p class="form-hint" style="margin-top: -0.5rem;">Minimum 8 characters.</p>

        <div class="btn-group">
            <a href="{{ route('install.application') }}" class="btn btn-secondary">← Back</a>
            <button type="submit" class="btn btn-primary">Continue →</button>
        </div>
    </form>
@endsection
