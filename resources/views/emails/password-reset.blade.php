<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wachtwoord opnieuw instellen</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 0; }
        .wrapper { max-width: 560px; margin: 40px auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
        .header { background: {{ $primaryColor }}; padding: 28px 32px; text-align: center; }
        .header h1 { color: #fff; margin: 0; font-size: 22px; }
        .body { padding: 32px; color: #333; font-size: 15px; line-height: 1.6; }
        .button { display: inline-block; margin: 24px 0; padding: 14px 32px; background: {{ $primaryColor }}; color: #fff; text-decoration: none; border-radius: 6px; font-size: 16px; font-weight: bold; }
        .note { font-size: 13px; color: #888; margin-top: 24px; }
        .footer { padding: 16px 32px; background: #f9f9f9; font-size: 12px; color: #aaa; text-align: center; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="header">
        <h1>{{ $headerText }}</h1>
    </div>
    <div class="body">
        <p>Hallo {{ $recipientName }},</p>
        @if($introText)
            <p>{{ $introText }}</p>
        @else
            <p>We hebben een verzoek ontvangen om het wachtwoord van jouw account opnieuw in te stellen. Klik op de knop hieronder om een nieuw wachtwoord in te stellen.</p>
        @endif

        <a href="{{ $resetUrl }}" class="button">{{ $buttonText }}</a>

        <p>Werkt de knop niet? Kopieer dan deze link in je browser:</p>
        <p style="word-break:break-all; color:#555; font-size:13px;">{{ $resetUrl }}</p>

        <p class="note">Je hebt dit niet aangevraagd? Dan kun je deze mail veilig negeren. Je wachtwoord blijft ongewijzigd.</p>
        <p class="note">Deze link is <strong>60 minuten</strong> geldig.</p>
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
