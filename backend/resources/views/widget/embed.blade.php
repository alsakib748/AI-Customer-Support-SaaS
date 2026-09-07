{{-- resources/views/widget/embed.blade.php --}}
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat Widget</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: transparent;
            overflow: hidden;
        }

        #app {
            width: 100%;
            height: 100vh;
        }
    </style>
</head>

<body>
    <div id="app"></div>

    <script>
        window.WIDGET_CONFIG = {
            widgetId: '{{ $widgetId }}',
            apiUrl: '{{ $apiUrl }}',
        };
    </script>

    @vite('resources/js/widget.js')
</body>

</html>