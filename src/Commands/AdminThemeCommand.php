<?php
namespace Funseco\Looks\Commands;

use Funseco\Core\Models\Player;
use Funseco\Core\Services\Auth\Permissions;
use Funseco\Core\SharedKernel\Commands\Command;
use Funseco\Core\SharedKernel\Commands\DeclaresCommand;
use Funseco\Core\SharedKernel\Commands\Described;
use Funseco\Core\SharedKernel\Commands\Guarded;
use Funseco\Core\SharedKernel\Enums\Role;
use Funseco\Core\SharedKernel\UI\Theme\ThemeRegistry;
use Funseco\Looks\Text;

/** /admin theme <name|list> — the server default theme, until the next restart. */
class AdminThemeCommand implements Command, Guarded, Described
{
    use DeclaresCommand;

    protected string $name = 'admin theme';
    protected ?Role $role = Role::Operator;
    protected ?string $ability = 'theme';
    protected string $description = 'Selects default UI theme';
    protected string $usage = '<theme name|list>';

    public function handle(Player $player, array $args): void
    {
        $arg = trim((string) ($args[0] ?? ''));
        $themes = app(ThemeRegistry::class);

        if ($arg === '' || strtolower($arg) === 'list') {
            Text::tell($player, Text::get('theme_list', implode(', ', $themes->names()), $themes->current()->name()));
        } elseif ($themes->find($arg) === null) {
            Text::tell($player, Text::get('theme_invalid', implode(', ', $themes->names())));
        } elseif ($themes->use($arg)) {
            Text::broadcast(Text::get('admin_theme_set', app(Permissions::class)->title($player), Text::nick($player), $themes->current()->name()));
        }
    }
}
