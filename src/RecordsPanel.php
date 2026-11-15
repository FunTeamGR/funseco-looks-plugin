<?php
namespace Funseco\Looks;

use Funseco\Core\Models\Map;
use Funseco\Core\Models\Player;
use Funseco\Core\Services\Cache\CurrentMap;
use Funseco\Core\Services\Cache\CurrentPlayers;
use Funseco\Core\Services\Cache\ServerInfo;
use Funseco\Core\Services\Looks\PlayerLooks;
use Funseco\Core\Services\ManiaLink\Facades\ManiaLink;
use Funseco\Core\SharedKernel\CurrentDedimaniaRecords;
use Funseco\Core\SharedKernel\CurrentLocalRecords;
use Funseco\Core\SharedKernel\FinishHistory;
use Funseco\Core\SharedKernel\TrackExchange;

/**
 * The records panel (XAseco panels/Records*.xml): your best (PB), the local #1, the Dedimania #1
 * and the TMX world record of the current map, each a button that opens the matching list.
 * Which template a player sees is their own choice (/recpanel). Shown from map start, hidden at
 * the score screen. Everything is read through core contracts; nothing here knows the plugins.
 */
class RecordsPanel
{
    public const ID = 'records_panel';
    private const STUNTS = 4;
    private const ACTIONS = ['pb' => 'records_pb', 'local' => 'records_local', 'dedi' => 'records_dedi', 'tmx' => 'records_tmx'];

    /** Command each row's button runs. */
    public const COMMANDS = ['pb' => 'pb', 'local' => 'recs', 'dedi' => 'dedirecs', 'tmx' => 'tmxrecs'];

    public static function actions(): array
    {
        return array_values(self::ACTIONS);
    }

    /** Template names (full, with the 'Records' prefix) this panel can draw. */
    public static function templates(): array
    {
        return array_map(fn ($n) => 'Records'.$n, array_keys(config('panels.records', [])));
    }

    // ---- state ------------------------------------------------------------------------

    public function atScore(): bool
    {
        return (bool) state()->get('looks:records:score', false);
    }

    public function setScore(bool $score): void
    {
        state()->forever('looks:records:score', $score);
    }

    /** The player's best on the current map (ms, or points in Stunts), null when unknown. */
    public function pb(Player $player): ?int
    {
        return state()->get('looks:records:pb:'.$player->login);
    }

    /** Read the player's best from the local records, else from their stored finishes. */
    public function loadPb(Player $player): void
    {
        $map = CurrentMap::get();
        $best = null;
        if ($player->id && ($local = app(CurrentLocalRecords::class)->records()->first(fn ($r) => $r->playerId() === $player->id))) {
            $best = $local->score();
        } elseif ($player->id && $map instanceof Map && $map->exists) {
            $best = app(FinishHistory::class)->bestScores($player)[$map->id] ?? null;
        }
        $this->storePb($player, $best > 0 ? $best : null);
    }

    public function loadAllPbs(): void
    {
        foreach (CurrentPlayers::all() as $player) {
            $this->loadPb($player);
        }
    }

    /** A finish: the PB moves when the score is better (lower; higher in Stunts). True when it did. */
    public function finished(Player $player, int $score): bool
    {
        $pb = $this->pb($player);
        $better = $pb === null || (ServerInfo::getGameMode() === self::STUNTS ? $score > $pb : $score < $pb);
        $better && $this->storePb($player, $score);

        return $better;
    }

    /** Local records changed: anybody's ranked record counts towards their PB; then everybody is redrawn. */
    public function localChanged(): void
    {
        foreach (app(CurrentLocalRecords::class)->records() as $record) {
            if (($player = CurrentPlayers::all()->first(fn ($p) => $p->id === $record->playerId())) && !$record->hasNoTime) {
                $this->finished($player, $record->score());
            }
        }
        $this->showToAll();
    }

    public function forget(Player $player): void
    {
        state()->forget('looks:records:pb:'.$player->login);
    }

    private function storePb(Player $player, ?int $score): void
    {
        $score === null ? $this->forget($player) : state()->forever('looks:records:pb:'.$player->login, $score);
    }

    // ---- values -----------------------------------------------------------------------

    private function stunts(): bool
    {
        return ServerInfo::getGameMode() === self::STUNTS;
    }

    /** m:ss.hh, or the score padded to 5 characters in Stunts; the "empty" texts for null. */
    private function format(?int $score): string
    {
        if ($this->stunts()) {
            return $score === null ? '  ---' : sprintf('%5d', $score);
        }
        if ($score === null) {
            return '   --.--';
        }

        return sprintf('%d:%02d.%02d', intdiv($score, 60000), intdiv($score % 60000, 1000), intdiv($score % 1000, 10));
    }

    /** @return array{pb: string, local: string, dedi: string, tmx: string} */
    public function values(Player $player): array
    {
        $local = app(CurrentLocalRecords::class)->records()->first();
        $dedi = $this->stunts() ? null : app(CurrentDedimaniaRecords::class)->records()->first();
        $tmx = app(TrackExchange::class)->current()?->worldRecord();

        return [
            'pb'    => $this->format($this->pb($player)),
            'local' => $this->format($local && !$local->hasNoTime ? $local->score() : null),
            'dedi'  => $this->format($dedi && !$dedi->hasNoTime ? $dedi->score() : null),
            'tmx'   => $this->format($tmx ? ($this->stunts() ? $tmx->score : $tmx->time) : null),
        ];
    }

    // ---- drawing ----------------------------------------------------------------------

    public function showToAll(): void
    {
        foreach (CurrentPlayers::all() as $player) {
            $this->showTo($player);
        }
    }

    public function showTo(Player $player): void
    {
        if ($this->atScore() || !CurrentMap::get()) {
            return;
        }
        $xml = $this->xml(app(PlayerLooks::class)->panel($player, 'records'), $this->values($player));
        ManiaLink::displayLogin($player, $xml ?? self::empty());
    }

    public function hideFromAll(): void
    {
        ManiaLink::displayAll(self::empty());
    }

    public static function empty(): string
    {
        return '<manialink id="'.ManiaLink::getAction(self::ID).'"></manialink>';
    }

    /**
     * XML for a template given by full name; null for '' or an unknown name.
     *
     * @param array{pb: string, local: string, dedi: string, tmx: string} $values
     */
    public function xml(string $name, array $values): ?string
    {
        $t = config('panels.records.'.substr($name, strlen('Records')));
        if ($name === '' || !is_array($t)) {
            return null;
        }

        $xml = '<manialink id="'.ManiaLink::getAction(self::ID).'"><frame posn="'.$t['frame'].'">';
        if (!empty($t['bg'])) {
            $xml .= '<quad sizen="'.$t['bg'][0].' '.$t['bg'][1].'" style="Bgs1InRace" substyle="NavButton"/>';
        }

        $xml .= $this->column($t['label'], $t['rows'], fn ($i, $row) => ['text' => $t['label']['texts'][$i], 'action' => null]);
        $xml .= $this->column($t['value'], $t['rows'], fn ($i, $row) => [
            'text'   => ($t['value']['prefix'][$i] ?? '').$values[$row],
            'action' => ManiaLink::getAction(self::ACTIONS[$row]),
        ]);

        return $xml.'</frame></manialink>';
    }

    /** One column of labels: a <format> line first when the template sets one. */
    private function column(array $c, array $rows, \Closure $cell): string
    {
        $xml = isset($c['format']) ? '<format '.$c['format'].'/>' : '';
        foreach ($rows as $i => $row) {
            ['text' => $text, 'action' => $action] = $cell($i, $row);
            $x = is_array($c['x']) ? $c['x'][$i] : $c['x'];
            $scale = $c['scale'][$i] ?? 1;
            $xml .= '<label posn="'.$x.' '.$c['ys'][$i].' 2" sizen="'.$c['size'][0].' '.$c['size'][1].'"'
                .($c['align'] ? ' '.$c['align'] : '')
                .(isset($c['style']) ? ' style="'.$c['style'].'"' : '')
                .($scale != 1 ? ' scale="'.$scale.'"' : '')
                .' text="'.htmlspecialchars($text).'"'
                .($action !== null ? ' action="'.$action.'"' : '').'/>';
        }

        return $xml;
    }
}
