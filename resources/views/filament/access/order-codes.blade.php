{{-- De kaarten van één bestelling: wat er gekocht is, en de codes met hun QR.
     Dezelfde codes staan in de mail van de koper; dit scherm is voor als hij
     belt omdat die mail niet is aangekomen. --}}
<div class="space-y-5 py-2">
    <div class="text-sm text-gray-500 dark:text-gray-400">
        {{ $order->buyer_name }} · {{ $order->buyer_email }}<br>
        {{ $order->agendaItem?->title }}
        @if ($order->agendaItem?->starts_at)
            · {{ $order->agendaItem->starts_at->format('d-m-Y H:i') }}
        @endif
    </div>

    <div class="rounded-lg border border-gray-200 dark:border-gray-700 divide-y divide-gray-200 dark:divide-gray-700">
        @foreach ($order->lines as $line)
            <div class="flex items-center justify-between px-3 py-2 text-sm">
                <span>{{ $line->quantity }}× {{ $line->type_name }}</span>
                <span class="text-gray-500 dark:text-gray-400">
                    {{ \App\Filament\Resources\OrderResource::bedrag($line->line_total_cents) }}
                </span>
            </div>
        @endforeach
        <div class="flex items-center justify-between px-3 py-2 text-sm font-bold">
            <span>Totaal</span>
            <span>{{ \App\Filament\Resources\OrderResource::bedrag($order->total_cents) }}</span>
        </div>
    </div>

    @if ($order->accessCodes->isEmpty())
        <p class="text-sm text-gray-500 dark:text-gray-400">
            Er zijn nog geen kaarten aangemaakt voor deze bestelling.
        </p>
    @else
        <div class="grid grid-cols-2 gap-3">
            @foreach ($order->accessCodes as $code)
                <div class="flex flex-col items-center gap-2 rounded-lg border border-gray-200 dark:border-gray-700 p-3">
                    <img
                        src="{{ \App\Support\Qr::pngDataUri($code->code, 200) }}"
                        alt="QR-code {{ $code->code }}"
                        class="w-32 h-32 rounded bg-white"
                    >
                    <p class="font-mono text-sm font-bold tracking-wider">{{ $code->code }}</p>
                    @if ($code->label)
                        <p class="text-xs text-gray-500 dark:text-gray-400 text-center">{{ $code->label }}</p>
                    @endif
                    @if (! $code->is_active)
                        <p class="text-xs font-medium text-danger-600">Ingetrokken</p>
                    @elseif ($code->used_count > 0)
                        <p class="text-xs text-gray-500 dark:text-gray-400">Gebruikt</p>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>
