<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'BorneOPS') }}</title>
        @viteReactRefresh
        @vite(['resources/js/app.tsx'])
    </head>
    <body>
        <div id="app"></div>
    </body>
</html>
