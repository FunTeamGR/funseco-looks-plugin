<?php
namespace Funseco\Looks\Commands;

use Funseco\Core\Models\Player;
use Funseco\Core\Services\Looks\PlayerLooks;
use Funseco\Core\SharedKernel\Commands\Command;
use Funseco\Core\SharedKernel\Commands\DeclaresCommand;
use Funseco\Core\SharedKernel\Commands\Described;
use Funseco\Core\SharedKernel\UI\Window;
use Funseco\Looks\Text;

/**
 * Shared flow of /style and the panel commands: help, list, <number>, default, a name.
 * The last shown list is remembered per player so a number or a click picks from it.
 */
abstract class ChoiceCommand implements Command, Described
{
    use DeclaresCommand;

    /** Title word in help/list headers: 'style' or 'admin', 'donate', 'vote'. */
    abstract protected function noun(): string;

    /** Names shown by "list" (without the default/off entries). */
    abstract protected function names(Player $player): array;

    /** Apply a choice: a name (any case, with or without prefix), 'default' or 'off'; $echo is what "selected" messages show. */
    abstract protected function choose(Player $player, string $name, string $echo): void;

    /** Re-open the list after a click (styles redraw in the new look, panels do not). */
    protected function relistAfterClick(): bool
    {
        return false;
    }

    protected function listHeader(): string
    {
        return 'Currently available '.$this->noun().($this->noun() === 'style' ? '' : ' panel').'s:';
    }

    protected function helpHeader(): string
    {
        return '{#black}/'.$this->name.'$g will change the '.($this->noun() === 'style' ? 'window style' : $this->noun().' panel').':';
    }

    protected function hasOff(): bool
    {
        return true;
    }

    protected function looks(): PlayerLooks
    {
        return app(PlayerLooks::class);
    }

    public function handle(Player $player, array $args): void
    {
        $arg = trim((string) ($args[0] ?? ''));
        $lower = strtolower($arg);

        if ($arg === '') {
            $this->none($player);
        } elseif ($lower === 'help') {
            $this->help($player);
        } elseif ($lower === 'list') {
            $this->list($player);
        } elseif (ctype_digit($arg) && ($list = state()->get('looks:list:'.$player->login)) && ($list['kind'] ?? null) === $this->listKind()) {
            $entry = $list['names'][(int) $arg - 1] ?? null;
            $entry === null ? $this->invalid($player) : $this->choose($player, $entry, $arg);
        } else {
            $this->choose($player, $arg, $arg);
        }
    }

    abstract protected function none(Player $player): void;

    abstract protected function invalid(Player $player): void;

    /** Identifies the list a remembered number refers to. */
    protected function listKind(): string
    {
        return $this->noun();
    }

    private function help(Player $player): void
    {
        $noun = $this->noun() === 'style' ? 'style' : $this->noun().' panel';
        $plural = $this->noun() === 'style' ? 'styles' : 'panels';
        $rows = [
            ['...', '{#black}help', 'Displays this help information'],
            ['...', '{#black}list', 'Displays available '.$plural],
            ['...', '{#black}default', 'Resets '.($this->noun() === 'style' ? 'style' : 'panel').' to server default'],
        ];
        if ($this->hasOff()) {
            $rows[] = ['...', '{#black}off', 'Disables '.$noun];
        }
        $rows[] = ['...', '{#black}xxx', 'Selects '.$noun.' xxx'];

        Window::show($player, $this->helpHeader(), $rows, [0.8, 0.05, 0.15, 0.6], ['Icons64x64_1', 'TrackInfo', -0.01]);
    }

    private function list(Player $player): void
    {
        $max = (int) config('looks-plugin.list_max.'.$this->noun(), 50);
        $names = array_slice($this->names($player), 0, $max);
        $names[] = 'default';
        if ($this->hasOff()) {
            $names[] = 'off';
        }
        state()->forever('looks:list:'.$player->login, ['kind' => $this->listKind(), 'names' => $names]);

        $rows = [];
        foreach ($names as $i => $name) {
            $n = $i + 1;
            $label = '{#black}'.$name;
            $rows[] = [sprintf('%02d.', $n), $n <= (int) config('looks-plugin.clickable_rows', 60) ? [$label, 'looks_pick_'.$n] : $label];
        }

        Window::show($player, $this->listHeader(), $rows, [0.8, 0.1, 0.7], ['Icons64x64_1', 'Windowed']);
    }

    /** A click on list row $n. */
    public function pick(Player $player, int $n): void
    {
        $list = state()->get('looks:list:'.$player->login);
        if (($list['kind'] ?? null) !== $this->listKind() || !isset($list['names'][$n - 1])) {
            return;
        }
        $this->choose($player, $list['names'][$n - 1], $list['names'][$n - 1]);
        if ($this->relistAfterClick()) {
            $this->list($player);
        }
    }

    /** Exposed so the click handler can reach the list kind. */
    public function kind(): string
    {
        return $this->listKind();
    }
}
