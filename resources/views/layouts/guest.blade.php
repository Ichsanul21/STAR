<!DOCTYPE html>
<html lang="id" data-theme="alenkosa">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'STAR' }} · STAR</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen">
    <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        @if ($errors->any())
            <div class="mx-auto mb-4 max-w-md rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
            </div>
        @endif
        @yield('content')
    </main>

    @php($uiWatermark = app(\App\Services\UiWatermarkService::class)->payload(null, request()))
    @if ($uiWatermark['enabled'] && $uiWatermark['login_enabled'])
        <x-ui-watermark :line1="$uiWatermark['line1']" :line2="$uiWatermark['line2']" :opacity="$uiWatermark['opacity']" :color="$uiWatermark['color']" :speed="$uiWatermark['speed']" :font-size="$uiWatermark['font_size']" />
    @endif
</body>
</html>
