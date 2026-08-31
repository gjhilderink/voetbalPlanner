{{-- De QR van één toegangscode, met de code eronder in cijfers voor als een
     scanner het plaatje niet leest. De download loopt via een data-URI: dat
     scheelt een route die ook weer afgeschermd zou moeten worden. --}}
<div class="flex flex-col items-center gap-4 py-2">
    <img
        src="{{ $dataUri }}"
        alt="QR-code {{ $code->code }}"
        class="w-64 h-64 rounded-lg border border-gray-200 dark:border-gray-700 bg-white"
    >

    <div class="text-center">
        <p class="font-mono text-lg font-bold tracking-widest text-gray-950 dark:text-white">
            {{ $code->code }}
        </p>
        @if ($code->label)
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $code->label }}</p>
        @endif
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            {{ $code->agendaItem?->title }}
            @if ($code->agendaItem?->starts_at)
                · {{ $code->agendaItem->starts_at->format('d-m-Y H:i') }}
            @endif
        </p>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            {{ $code->used_count }} van {{ $code->max_uses }} keer gebruikt
        </p>
    </div>

    <a
        href="{{ $download }}"
        download="toegangscode-{{ $code->code }}.png"
        class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-3 py-2 text-sm font-medium text-white hover:bg-primary-500"
    >
        Downloaden als afbeelding
    </a>
</div>
