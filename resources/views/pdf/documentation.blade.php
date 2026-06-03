<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }

  body {
    font-family: DejaVu Sans, Arial, sans-serif;
    font-size: 10pt;
    color: #1f2937;
    line-height: 1.6;
  }

  .cover {
    padding: 60px 50px;
    background: #1e3a5f;
    color: #fff;
    min-height: 140px;
    margin-bottom: 30px;
  }

  .cover h1 {
    font-size: 24pt;
    font-weight: bold;
    letter-spacing: 0.5px;
  }

  .cover p {
    margin-top: 8px;
    font-size: 10pt;
    opacity: 0.8;
  }

  .content {
    padding: 0 50px 40px;
  }

  .category-heading {
    font-size: 14pt;
    font-weight: bold;
    color: #1e3a5f;
    border-bottom: 2px solid #1e3a5f;
    padding-bottom: 5px;
    margin: 28px 0 16px;
    page-break-after: avoid;
  }

  .section {
    margin-bottom: 20px;
    page-break-inside: avoid;
  }

  .section-title {
    font-size: 11pt;
    font-weight: bold;
    color: #374151;
    margin-bottom: 6px;
  }

  .section-body {
    font-size: 9.5pt;
    color: #4b5563;
    white-space: pre-wrap;
    word-wrap: break-word;
  }

  .footer {
    position: fixed;
    bottom: 20px;
    left: 50px;
    right: 50px;
    font-size: 8pt;
    color: #9ca3af;
    border-top: 1px solid #e5e7eb;
    padding-top: 6px;
    display: flex;
    justify-content: space-between;
  }
</style>
</head>
<body>

<div class="cover">
  <h1>VoetbalPlanner — Documentatie</h1>
  <p>Gegenereerd op {{ $generatedAt }}</p>
</div>

<div class="content">
  @foreach ($categoryLabels as $key => $label)
    @if (isset($sections[$key]) && $sections[$key]->isNotEmpty())
      <div class="category-heading">{{ $label }}</div>

      @foreach ($sections[$key]->sortBy('sort_order') as $section)
        <div class="section">
          <div class="section-title">{{ $section->title }}</div>
          <div class="section-body">{{ $section->body }}</div>
        </div>
      @endforeach
    @endif
  @endforeach
</div>

<div class="footer">
  <span>VoetbalPlanner — Documentatie</span>
  <span>Gegenereerd op {{ $generatedAt }}</span>
</div>

</body>
</html>
