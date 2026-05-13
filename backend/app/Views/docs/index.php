<!DOCTYPE html>
<html>
<head>
    <title>API Reference</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
</head>
<body>
    <div id="app"></div>
    <script src="https://cdn.jsdelivr.net/npm/@scalar/api-reference"></script>
    <script>
        const docsPageUrl = new URL(window.location.href);
        const docsBaseUrl = new URL(docsPageUrl.pathname.endsWith('/') ? docsPageUrl.pathname : `${docsPageUrl.pathname}/`, docsPageUrl.origin);

        Scalar.createApiReference('#app', {
            url: new URL('spec', docsBaseUrl).toString(),
            authentication: {
                preferredSecurityScheme: 'bearerAuth'
            },
            persistAuth: false
        });
    </script>
</body>
</html>
