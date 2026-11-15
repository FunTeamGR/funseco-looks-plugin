<?php
namespace Funseco\Looks\Commands;

use Funseco\Core\Models\Player;
use Funseco\Core\SharedKernel\Commands\Guarded;
use Funseco\Core\SharedKernel\Enums\Role;
use Funseco\Looks\Text;

/**
 * /donpanel, /votepanel, /admin panel: a player's own panel template per kind.
 * One instance per kind; the admin panel is staff-only (ability 'panel').
 */
class PanelCommand extends ChoiceCommand implements Guarded
{
    private const COMMANDS = [
        'admin'  => ['admin panel', 'Selects admin panel (see: /admin panel help)'],
        'donate' => ['donpanel', 'Selects donate panel (see: /donpanel help)'],
        'records' => ['recpanel', 'Selects records panel (see: /recpanel help)'],
        'vote'   => ['votepanel', 'Selects vote panel (see: /votepanel help)'],
    ];

    protected string $name;
    protected string $description;

    public function __construct(private string $kind)
    {
        [$this->name, $this->description] = self::COMMANDS[$kind];
    }

    public function role(): ?Role
    {
        return $this->kind === 'admin' ? Role::Operator : null;
    }

    public function ability(): ?string
    {
        return $this->kind === 'admin' ? 'panel' : null;
    }

    protected function noun(): string
    {
        return $this->kind;
    }

    protected function names(Player $player): array
    {
        $prefix = ucfirst($this->kind);

        return array_map(fn ($n) => str_starts_with($n, $prefix) ? substr($n, strlen($prefix)) : $n, $this->looks()->panelNames($this->kind));
    }

    protected function none(Player $player): void
    {
        Text::tell($player, Text::get('panel_none', $this->kind, '/'.$this->name));
    }

    protected function invalid(Player $player): void
    {
        Text::tell($player, Text::get('panel_invalid', $this->kind, '/'.$this->name));
    }

    protected function choose(Player $player, string $name, string $echo): void
    {
        $label = ucfirst($this->kind);
        $lower = strtolower($name);

        if ($lower === 'off') {
            $this->looks()->setPanel($player, $this->kind, '');
            Text::tell($player, Text::get('panel_off', $label));
        } elseif ($lower === 'default') {
            $default = $this->looks()->defaultPanel($this->kind);
            $this->looks()->setPanel($player, $this->kind, $default);
            Text::tell($player, Text::get('panel_default', $label, $this->short($default)));
        } elseif ($panel = $this->looks()->findPanel($this->kind, $name)) {
            $this->looks()->setPanel($player, $this->kind, $panel);
            Text::tell($player, Text::get('panel_selected', $label, htmlspecialchars($echo, ENT_NOQUOTES)));
        } else {
            $this->invalid($player);
        }
    }

    private function short(string $name): string
    {
        $prefix = ucfirst($this->kind);

        return str_starts_with($name, $prefix) ? substr($name, strlen($prefix)) : $name;
    }

    protected function listKind(): string
    {
        return 'panel:'.$this->kind;
    }
}
