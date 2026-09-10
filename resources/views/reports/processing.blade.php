<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <meta http-equiv="refresh" content="4;url={{ $reportUrl }}">
    <title>{{ $title }} — Generando…</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
            background: #f3f4f6;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #111827;
        }
        .card {
            background: #ffffff;
            border-radius: 16px;
            padding: 40px 48px;
            max-width: 460px;
            width: 90%;
            text-align: center;
            box-shadow: 0 10px 25px rgba(0, 0, 0, .08);
        }
        .spinner {
            width: 44px;
            height: 44px;
            margin: 0 auto 20px;
            border: 4px solid #e5e7eb;
            border-top-color: #2563eb;
            border-radius: 50%;
            animation: spin .8s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        h1 { font-size: 20px; font-weight: 700; margin-bottom: 8px; }
        p { color: #4b5563; font-size: 14px; line-height: 1.5; }
        .filename { margin-top: 12px; color: #6b7280; font-size: 12px; word-break: break-all; }
        a { display: inline-block; margin-top: 20px; color: #2563eb; font-size: 14px; text-decoration: none; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="card">
        <div class="spinner" aria-hidden="true"></div>
        <h1>Generando {{ $title }}</h1>
        <p>El archivo se está preparando y se descargará automáticamente en unos momentos. Esta página se refresca sola.</p>
        @if (! empty($filename))
            <div class="filename">{{ $filename }}</div>
        @endif
        <a href="{{ $reportUrl }}">Descargar el documento ahora</a>
    </div>
</body>
</html>