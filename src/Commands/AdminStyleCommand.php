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

/** /admin style <name> — the server default window style, until the next restart. */
class AdminStyleCommand implements Command, Guarded, Described
{
    use DeclaresCommand;

    protected string $name = 'admin style';
    protected ?Role $role = Role::Operator;
    protected ?string $ability = 'style';
    protected string $description = 'Selects default window style';
    protected string $usage = '<style name>';

    public function handle(Player $player, array $args): void
    {
        $arg = trim((string) ($args[0] ?? ''));
        $looks = app(PlayerLooks::class);

        if ($arg === '') {
            Text::tell($player, Text::get('admin_missing', 'style'));   // falls through to the admin plugin's answer
        } elseif (strtolower($arg) === 'off') {
            $looks->setDefaultStyle('');
            Text::broadcast(Text::get('admin_style_reset', app(Permissions::class)->title($player), Text::nick($player)));
        } elseif ($style = $looks->findStyle($arg)) {
            $looks->setDefaultStyle($style);
            Text::broadcast(Text::get('admin_style_set', app(Permissions::class)->title($player), Text::nick($player), $style));
        } else {
            Text::tell($player, Text::get('style_invalid'));
        }
    }
}
