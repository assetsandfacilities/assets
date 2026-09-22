<!DOCTYPE html>
<html lang="en">
<head>
    @include('partials.favicon')
    <meta charset="utf-8">
    <title>500 - Server Error</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @include('errors.partials.style')
</head>
<body>
    <header class="err-bar">
        <div class="err-mark"><img src="{{ asset('images/nu-logo.png') }}" alt="National University"></div>
        <div class="err-brand">NU Clark<span>Asset Management</span></div>
    </header>
    <main class="err-main">
        <div class="err-code">500</div>
        <div class="err-eyebrow">Error 500 · Server</div>
        <h1>Something went wrong</h1>
        <p>An unexpected server error occurred while processing your request.</p>
        @auth
            @if(auth()->user()->isAdmin())
                <p class="reason mono">
                    {{ get_class($exception) }}: {{ $exception->getMessage() }}<br>
                    @if(method_exists($exception, 'getFile'))
                        {{ basename($exception->getFile()) }}:{{ $exception->getLine() }}
                    @endif
                </p>
                <div class="err-note">
                    (Only visible to Admin/Super Admin accounts. If this mentions "Unknown column" or "no such column", run <code>php artisan migrate</code> on the server.)
                </div>
            @endif
        @endauth
        <a class="btn-back" href="{{ url()->previous() }}">Go Back</a>
    </main>
    <footer class="err-foot">National University — Clark</footer>
</body>
</html>
