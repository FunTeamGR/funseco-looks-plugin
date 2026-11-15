<?php
namespace Funseco\Looks\Commands;

use Funseco\Core\Models\Player;
use Funseco\Core\Services\Auth\Permissions;
use Funseco\Core\Services\Looks\PlayerLooks;
use Funseco\Core\SharedKernel\Commands\Command;
use Funseco\Core\SharedKernel\Commands\DeclaresCommand;
use Funseco\Core\SharedKernel\Commands\Described;
use Funseco\Core\SharedKernel\Commands\Guarded;
use Funseco\Core\SharedKernel\Enums\Role;
use Funseco\Looks\Text;

/** /admin admpanel|donpanel|recpanel|votepanel <name|off> — the server default panel of a kind, until restart. */
class AdminDefaultPanelCommand implements Command, Guarded, Described
{
    use DeclaresCommand;

    private const KINDS = ['admpanel' => 'admin', 'donpanel' => 'donate', 'recpanel' => 'records', 'votepanel' => 'vote'];

    protected string $name;
    protected ?Role $role = Role::Operator;
    protected ?string $ability;
    protected string $description;
    protected string $usage = '<panel name|off>';

    public function __construct(string $command)
    {
        $this->name = 'admin '.$command;
        $this->ability = $command;
        $this->description = 'Selects default '.self::KINDS[$command].' panel';
    }

    public function handle(Player $player, array $args): void
    {
        $kind = self::KINDS[$this->ability];
        $arg = trim((string) ($args[0] ?? ''));
        $looks = app(PlayerLooks::class);
        $title = app(Permissions::class)->title($player);
        $self = $kind === 'admin'; // admpanel talks to the caller only
        $prefix = ucfirst($kind);

        if ($arg === '') {
            Text::tell($player, Text::get('admin_missing', substr($this->name, strlen('admin ')).' '));
        } elseif (strtolower($arg) === 'off') {
            $looks->setDefaultPanel($kind, '');
            $this->say($player, $self, Text::get($self ? 'default_reset_self' : 'default_reset', $title, Text::nick($player), $kind));
        } elseif ($arg !== '' && ($panel = $looks->findPanel($kind, $arg))) {
            $looks->setDefaultPanel($kind, $panel);
            $short = str_starts_with($panel, $prefix) ? substr($panel, strlen($prefix)) : $panel;
            $this->say($player, $self, Text::get($self ? 'default_set_self' : 'default_set', $title, Text::nick($player), $kind, $short));
        } else {
            $command = $kind === 'admin' ? '/admin panel' : '/'.$this->ability;
            Text::tell($player, Text::get('panel_invalid', $kind, $command));
        }
    }

    private function say(Player $player, bool $callerOnly, string $message): void
    {
        $callerOnly ? Text::tell($player, $message) : Text::broadcast($message);
    }
}
