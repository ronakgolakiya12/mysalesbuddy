<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>MySalesBuddy</title>
    @vite(['resources/js/app.ts'])
</head>

<body class="antialiased bg-gray-50 text-gray-900">
    <div id="app"></div>
</body>

</html>
