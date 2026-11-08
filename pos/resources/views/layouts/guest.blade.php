@props(['title'])

<!doctype html>
<html lang="id">
<head>
    @include('partials.head')
</head>
<body class="bg-canvas font-sans text-body text-fg antialiased">
    <main class="mx-auto flex min-h-dvh max-w-100 flex-col justify-center px-4 py-8">
        {{ $slot }}
    </main>
</body>
</html>
