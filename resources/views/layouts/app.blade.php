<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Database Backup')</title>
    @stack('styles')
</head>
<body class="bg-gray-50 p-8 font-sans antialiased text-gray-900">
    @yield('content')
    @stack('scripts')
</body>
</html>
