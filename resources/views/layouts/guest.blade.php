<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title>{{ $title ?? 'Login' }} — Paccing CRM</title>
    @vite(['resources/css/app.css'])
    @livewireStyles
</head>
<body class="bg-gray-100 min-h-screen text-gray-900" style="max-width: 480px; margin: 0 auto;">
    {{ $slot }}
    @livewireScripts
</body>
</html>
