<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Database Backup')</title>
    <style>
        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        body {
            font-family: system-ui, sans-serif;
            background: #f0f2f5;
            margin: 0;
            padding: 30px 20px;
            color: #1a1a2e;
        }

        .card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
            max-width: 780px;
            margin: 0 auto;
            padding: 32px;
        }
    </style>
    @stack('styles')
</head>
<body>
    @yield('content')

    @stack('scripts')
</body>
</html>
