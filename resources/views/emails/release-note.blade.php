<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $titleLine }}</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 0; }
        .wrapper { max-width: 560px; margin: 40px auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
        .header { background: {{ $primaryColor }}; padding: 28px 32px; text-align: center; }
        .header h1 { color: #fff; margin: 0; font-size: 22px; }
        .body { padding: 32px; color: #333; font-size: 15px; line-height: 1.6; }
        .body h2 { color: {{ $primaryColor }}; font-size: 19px; margin: 0 0 16px; }
        .content { color: #333; font-size: 15px; line-height: 1.6; }
        .content p { margin: 0 0 12px; }
        .footer { padding: 16px 32px; background: #f9f9f9; font-size: 12px; color: #aaa; text-align: center; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="header">
        <h1>{{ $headerText }}</h1>
    </div>
    <div class="body">
        <h2>{{ $titleLine }}</h2>
        <div class="content">
            @if($bodyHtml !== '')
                {!! $bodyHtml !!}
            @endif
        </div>
    </div>
    <div class="footer">
        @if($footerText)
            {{ $footerText }}
        @else
            &copy; {{ date('Y') }} {{ config('app.name') }} &mdash; automatisch gegenereerd bericht
        @endif
    </div>
</div>
</body>
</html>
