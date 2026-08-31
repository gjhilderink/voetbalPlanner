<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reports;

use App\Exports\ClothingSizesExport;
use App\Filament\Support\TeamFilter;
use App\Models\ClothingItem;
use App\Models\Member;
use App\Models\MemberClothingSize;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Wie heeft welke maat, per elftal.
 *
 * De kolommen worden opgebouwd uit de kledingstukken van de club en staan dus
 * niet vast: de commissie beheert die lijst zelf, en het overzicht hoort mee te
 * bewegen zonder dat er code aan te pas komt.
 *
 * Een streepje betekent "nog niet opgegeven". Dat is de belangrijkste informatie
 * op dit scherm - je kijkt hier vooral om te zien wie je nog moet aanspreken.
 */
class ClothingSizes extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-swatch';
    protected static ?string $navigationLabel                = 'Kledingmaten per elftal';
    protected static ?string $title                          = 'Kledingmaten per elftal';
    protected static string|\UnitEnum|null $navigationGroup  = 'Rapportage';
    protected static ?int $navigationSort                    = 22;

    protected string $view = 'filament.pages.reports.clothing-sizes';

    /** @var array<int, string> */
    private const ROLLEN = ['super_admin', 'club_admin', 'kleding_commissie'];

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(self::ROLLEN) ?? false;
    }

    /**
     * De kledingstukken van deze club, in volgorde. Eén keer per verzoek.
     *
     * @return \Illuminate\Support\Collection<int, ClothingItem>
     */
    private function kledingstukken(): \Illuminate\Support\Collection
    {
        static $stukken = null;

        return $stukken ??= ClothingItem::query()
            ->when(
                filament()->getTenant(),
                fn (Builder $q) => $q->where('club_id', filament()->getTenant()->id),
            )
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportExcel')
                ->label('Excel exporteren')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(function (): BinaryFileResponse {
                    $teamId = $this->tableFilters['team_id']['value'] ?? null;

                    return Excel::download(
                        new ClothingSizesExport(
                            clubId: filament()->getTenant()?->id,
                            teamId: $teamId,
                        ),
                        'kledingmaten-' . now()->format('Y-m-d') . '.xlsx',
                    );
                }),
        ];
    }

    public function table(Table $table): Table
    {
        $stukken = $this->kledingstukken();

        return $table
            ->query(function (): Builder {
                $query = Member::query()
                    ->with(['clothingSizes.size', 'clothingSizes.item'])
                    ->where('is_active', true)
                    ->orderBy('name');

                if ($tenant = filament()->getTenant()) {
                    $query->whereHas('teams', fn (Builder $q) => $q->where('teams.club_id', $tenant->id));
                }

                return $query;
            })
            ->columns([
                TextColumn::make('name')
                    ->label('Lid')
                    ->searchable()
                    ->sortable(),

                // Eén kolom per kledingstuk. getStateUsing en geen relatie-kolom:
                // per rij moet de maat van dít stuk worden opgezocht, en dat is
                // met de relatie al ingeladen.
                ...$stukken->map(fn (ClothingItem $stuk) => TextColumn::make('kleding_' . $stuk->id)
                    ->label($stuk->name)
                    ->badge()
                    ->color(fn (?string $state): string => $state === null ? 'gray' : 'success')
                    // Maat en nummer in één badge: "M · 7". Een eigen kolom per
                    // nummer zou de tabel bij vijf kledingstukken verdubbelen,
                    // en dan past hij op geen enkel scherm meer.
                    ->getStateUsing(function (Member $record) use ($stuk): ?string {
                        $rij = $record->clothingSizes->firstWhere('clothing_item_id', $stuk->id);

                        if (! $rij?->size) {
                            return null;
                        }

                        return $rij->number === null
                            ? $rij->size->label
                            : $rij->size->label . ' · ' . $rij->number;
                    })
                    ->placeholder('—'))->all(),
            ])
            ->filters([
                SelectFilter::make('team_id')
                    ->label('Elftal')
                    ->options(fn (): array => TeamFilter::options())
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $q, $teamId) => $q->whereHas(
                            'teams',
                            fn (Builder $t) => $t->where('teams.id', $teamId),
                        ),
                    )),

                Filter::make('onvolledig')
                    ->label('Alleen onvolledig')
                    ->toggle()
                    ->query(function (Builder $query) use ($stukken): Builder {
                        if ($stukken->isEmpty()) {
                            return $query;
                        }

                        // Minder ingevulde maten dan er kledingstukken zijn. Zo
                        // vallen ook leden op die er de helft hebben staan; die
                        // zijn met een simpele "heeft niets"-controle onzichtbaar.
                        return $query->whereRaw(
                            '(select count(*) from member_clothing_sizes
                              where member_clothing_sizes.member_id = members.id
                                and member_clothing_sizes.clothing_item_id in ('
                            . implode(',', array_fill(0, $stukken->count(), '?'))
                            . ')) < ?',
                            [...$stukken->pluck('id')->all(), $stukken->count()],
                        );
                    }),
            ])
            ->actions([
                Action::make('maten')
                    ->label('Maten bewerken')
                    ->icon('heroicon-o-pencil-square')
                    ->modalHeading(fn (Member $record): string => 'Kledingmaten van ' . $record->name)
                    ->modalSubmitActionLabel('Opslaan')
                    // Per kledingstuk twee velden: de maat en het nummer. De
                    // sleutels krijgen een voorvoegsel, want een veldnaam die
                    // gelijk is aan het kledingstuk-id kan er maar één zijn.
                    ->fillForm(function (Member $record) use ($stukken): array {
                        $waarden = [];
                        foreach ($stukken as $stuk) {
                            $rij = $record->clothingSizes
                                ->firstWhere('clothing_item_id', $stuk->id);
                            $waarden['maat_' . $stuk->id] = $rij?->clothing_size_id;
                            $waarden['nr_' . $stuk->id] = $rij?->number;
                        }

                        return $waarden;
                    })
                    ->form($stukken->flatMap(fn (ClothingItem $stuk) => [
                        Forms\Components\Select::make('maat_' . $stuk->id)
                            ->label($stuk->name)
                            ->options($stuk->sizes->pluck('label', 'id')->all())
                            ->placeholder('Niet opgegeven')
                            ->columnSpan(2),
                        Forms\Components\TextInput::make('nr_' . $stuk->id)
                            ->label('Nummer')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(999)
                            ->placeholder('—')
                            ->columnSpan(1),
                    ])->all())
                    ->modalWidth('lg')
                    ->action(function (Member $record, array $data) use ($stukken): void {
                        foreach ($stukken as $stuk) {
                            $maatId = $data['maat_' . $stuk->id] ?? null;
                            $nummer = $data['nr_' . $stuk->id];
                            $nummer = ($nummer === null || $nummer === '')
                                ? null
                                : (int) $nummer;

                            // Zonder maat is er niets om een nummer aan te
                            // hangen: de rij bestaat per kledingstuk, en een
                            // nummer zonder maat is een halve opgave.
                            if (! $maatId) {
                                // Leeg = terug naar "niet opgegeven", en niet:
                                // laat staan wat er stond.
                                MemberClothingSize::where('member_id', $record->id)
                                    ->where('clothing_item_id', $stuk->id)
                                    ->delete();

                                continue;
                            }

                            MemberClothingSize::updateOrCreate(
                                ['member_id' => $record->id, 'clothing_item_id' => $stuk->id],
                                [
                                    'clothing_size_id'   => $maatId,
                                    'number'             => $nummer,
                                    'updated_by_user_id' => auth()->id(),
                                ],
                            );
                        }

                        Notification::make()
                            ->title('Maten opgeslagen')
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort('name')
            ->paginated([25, 50, 100]);
    }
}
