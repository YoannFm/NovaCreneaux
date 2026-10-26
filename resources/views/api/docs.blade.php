<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>API - {{ setting('app_name', 'NovaCreneaux') }}</title>
    <link rel="stylesheet" href="{{ asset('vendor/swagger-ui/swagger-ui.css') }}">
</head>
<body>
    <div id="swagger-ui"></div>
    <script src="{{ asset('vendor/swagger-ui/swagger-ui-bundle.js') }}"></script>
    <script>
        window.ui = SwaggerUIBundle({
            url: @json(asset('docs/openapi.yaml'), JSON_UNESCAPED_SLASHES),
            dom_id: '#swagger-ui',
            deepLinking: true,
        });
    </script>
</body>
</html>
