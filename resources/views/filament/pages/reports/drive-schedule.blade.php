<x-filament-panels::page>
    <div class="flex justify-end mb-2">
        <button onclick="window.print()"
                class="inline-flex items-center gap-1 px-3 py-1.5 text-sm font-medium rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300">
            <x-heroicon-o-printer class="w-4 h-4" />
            Afdrukken
        </button>
    </div>

    {{ $this->table }}
</x-filament-panels::page>
