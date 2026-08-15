<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Wedstrijd openen…</title>
    <style>
        body { font-family: Arial, sans-serif; background: #1e3a5f; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
        .card { background: #fff; border-radius: 12px; padding: 40px 32px; text-align: center; max-width: 380px; width: 90%; box-shadow: 0 4px 20px rgba(0,0,0,.2); }
        h2 { margin: 0 0 8px; color: #1e3a5f; }
        p { color: #555; font-size: 14px; line-height: 1.6; }
        .btn { display: inline-block; margin-top: 20px; padding: 12px 28px; background: #1e3a5f; color: #fff; border-radius: 6px; text-decoration: none; font-weight: bold; font-size: 15px; }
        .hint { margin-top: 18px; font-size: 13px; color: #888; }
    </style>
</head>
<body>
<div class="card">
    <h2>De app wordt geopend…</h2>
    <p>Gebeurt er niks? Tik op de knop hieronder.</p>
    <a href="{{ $deepLink }}" class="btn">Open in de app</a>
    <p class="hint">Heb je de app nog niet? Vraag je club om een uitnodiging.</p>
    <script>
        window.location.href = "{{ $deepLink }}";
    </script>
</div>
</body>
</html>
