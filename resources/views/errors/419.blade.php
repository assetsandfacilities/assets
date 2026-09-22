<!DOCTYPE html>
<html lang="en">
<head>
    @include('partials.favicon')
    <meta charset="utf-8">
    <title>419 - Session Expired</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @include('errors.partials.style')
</head>
<body>
    <header class="err-bar">
        <div class="err-mark"><img src="{{ asset('images/nu-logo.png') }}" alt="National University"></div>
        <div class="err-brand">NU Clark<span>Asset Management</span></div>
    </header>
    <main class="err-main">
        <div class="err-code">419</div>
        <div class="err-eyebrow">Error 419 · Session</div>
        <h1>Session Expired</h1>
        <p>The page sat idle long enough for your session to close. Sign in again to continue where you left off.</p>
        <a class="btn-back" href="{{ route('login') }}">Go to Sign In</a>
    </main>
    <footer class="err-foot">National University — Clark</footer>
</body>
</html>
