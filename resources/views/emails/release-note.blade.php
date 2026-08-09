<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subjectLine }}</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 0; }
        .wrapper { max-width: 560px; margin: 40px auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
        .header { background: {{ $primaryColor }}; padding: 28px 32px; text-align: center; }
        .header h1 { color: #fff; margin: 0; font-size: 22px; }
        .body { padding: 32px; color: #333; font-size: 15px; line-height: 1.6; }
        .note { padding-bottom: 20px; margin-bottom: 20px; border-bottom: 1px solid #eee; }
        .note:last-child { border-bottom: 0; margin-bottom: 0; padding-bottom: 0; }
        .note h2 { color: {{ $primaryColor }}; font-size: 19px; margin: 0 0 8px; }
        .badge { display: inline-block; font-size: 11px; font-weight: bold; text-transform: uppercase; letter-spacing: .5px; color: #fff; background: {{ $primaryColor }}; border-radius: 4px; padding: 3px 8px; margin-bottom: 10px; }
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
        @foreach($notes as $note)
            <div class="note">
                @php($typeLabel = \App\Models\ReleaseNote::$typeLabels[$note->type] ?? null)
                @if($typeLabel)
                    <span class="badge">{{ $typeLabel }}</span><br>
                @endif
                <h2>{{ $note->title }}</h2>
                <div class="content">
                    @if(trim((string) $note->body) !== '')
                        {!! $note->body !!}
                    @endif
                </div>
            </div>
        @endforeach
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
