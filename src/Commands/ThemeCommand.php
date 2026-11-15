<?php
namespace Funseco\Looks\Commands;

use Funseco\Core\Models\Player;
use Funseco\Core\SharedKernel\Commands\Command;
use Funseco\Core\SharedKernel\Commands\DeclaresCommand;
use Funseco\Core\SharedKernel\Commands\Described;
use Funseco\Core\SharedKernel\UI\Theme\ThemeRegistry;
use Funseco\Looks\Text;

/** /theme <name|list|default> — the player's own UI theme (kept across reconnects). */
class ThemeCommand implements Command, Described
{
    use DeclaresCommand;

    protected string $name = 'theme';
    protected string $description = 'Selects your UI theme';
    protected string $usage = '<theme name|list|default>';

    public function handle(Player $player, array $args): void
    {
        $arg = trim((string) ($args[0] ?? ''));
        $themes = app(ThemeRegistry::class);

        if ($arg === '' || strtolower($arg) === 'list') {
            Text::tell($player, Text::get('theme_list', implode(', ', $themes->names()), $themes->forPlayer($player)->name()));
        } elseif (strtolower($arg) === 'default') {
            $themes->setForPlayer($player, null);
            Text::tell($player, Text::get('theme_default', $themes->current()->name()));
        } elseif ($themes->setForPlayer($player, $arg)) {
            Text::tell($player, Text::get('theme_selected', $themes->forPlayer($player)->name()));
        } else {
            Text::tell($player, Text::get('theme_invalid', implode(', ', $themes->names())));
        }
    }
}
