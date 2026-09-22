<!DOCTYPE html>
<html lang="en">
<head>
    @include('partials.favicon')
    <meta charset="utf-8">
    <title>404 - Page Not Found</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @include('errors.partials.style')
</head>
<body>
    <header class="err-bar">
        <div class="err-mark"><img src="{{ asset('images/nu-logo.png') }}" alt="National University"></div>
        <div class="err-brand">NU Clark<span>Asset Management</span></div>
    </header>
    <main class="err-main">
        <div class="err-code">404</div>
        <div class="err-eyebrow">Error 404 · Not found</div>
        <h1>Page Not Found</h1>
        <p>That address doesn't match anything in the system. It may have been moved or the link may be incomplete.</p>
        <a class="btn-back" href="{{ url('/') }}">Back to Home</a>
    </main>
    <footer class="err-foot">National University — Clark</footer>
</body>
</html>
