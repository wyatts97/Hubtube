@extends('install.layout')

@section('steps')
    <div class="step-dot done"></div>
    <div class="step-dot active"></div>
    <div class="step-dot"></div>
    <div class="step-dot"></div>
    <div class="step-dot"></div>
@endsection

@section('content')
    <h2 class="card-title">Step 2: Database Configuration</h2>
    <p class="card-desc">Enter your database credentials. The database will be created automatically if it doesn't exist.</p>

    @if($errors->has('db_connection'))
        <div class="alert alert-error">{{ $errors->first('db_connection') }}</div>
    @endif

    <form method="POST" action="{{ route('install.database.save') }}">
        @csrf

        <div class="form-group">
            <label for="db_connection">Database Driver</label>
            <select name="db_connection" id="db_connection">
                <option value="mysql" {{ old('db_connection', $current['db_connection']) === 'mysql' ? 'selected' : '' }}>MySQL</option>
                <option value="mariadb" {{ old('db_connection', $current['db_connection']) === 'mariadb' ? 'selected' : '' }}>MariaDB</option>
            </select>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="db_host">Host</label>
                <input type="text" name="db_host" id="db_host" value="{{ old('db_host', $current['db_host']) }}" />
            </div>
            <div class="form-group">
                <label for="db_port">Port</label>
                <input type="number" name="db_port" id="db_port" value="{{ old('db_port', $current['db_port']) }}" />
            </div>
        </div>

        <div class="form-group">
            <label for="db_database">Database Name</label>
            <input type="text" name="db_database" id="db_database" value="{{ old('db_database', $current['db_database']) }}" />
            <p class="form-hint">Will be created if it doesn't exist.</p>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="db_username">Username</label>
                <input type="text" name="db_username" id="db_username" value="{{ old('db_username', $current['db_username']) }}" />
            </div>
            <div class="form-group">
                <label for="db_password">Password</label>
                <input type="password" name="db_password" id="db_password" value="{{ old('db_password', '') }}" placeholder="Leave empty if none" />
            </div>
        </div>

        <div class="btn-group">
            <a href="{{ route('install.requirements') }}" class="btn btn-secondary">← Back</a>
            <button type="submit" class="btn btn-primary">Test &amp; Save →</button>
        </div>
    </form>
@endsection
