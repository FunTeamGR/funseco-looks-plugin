<?php
namespace Funseco\Looks\Commands;

use Funseco\Core\Models\Player;
use Funseco\Looks\Text;

/** /style help|list|default|<name>|<number> — the player's own window style (kept across reconnects). */
class StyleCommand extends ChoiceCommand
{
    protected string $name = 'style';
    protected string $description = 'Selects window style (see: /style help)';

    protected function noun(): string
    {
        return 'style';
    }

    protected function names(Player $player): array
    {
        return $this->looks()->styleNames();
    }

    // The old TMN look ("off") is not built: it is not listed, but typing it is answered and
    // remembered as "no style of my own" (the server default keeps being drawn).
    protected function hasOff(): bool
    {
        return false;
    }

    protected function relistAfterClick(): bool
    {
        return true;
    }

    protected function none(Player $player): void
    {
        Text::tell($player, Text::get('style_none'));
    }

    protected function invalid(Player $player): void
    {
        Text::tell($player, Text::get('style_invalid'));
    }

    protected function choose(Player $player, string $name, string $echo): void
    {
        if (strtolower($name) === 'off') {
            $this->looks()->setStyle($player, '');
            Text::tell($player, Text::get('style_off'));
        } elseif (strtolower($name) === 'default') {
            $this->looks()->setStyle($player, null);
            Text::tell($player, Text::get('style_default', $this->looks()->defaultStyle()));
        } elseif ($style = $this->looks()->findStyle($name)) {
            $this->looks()->setStyle($player, $style);
            Text::tell($player, Text::get('style_selected', htmlspecialchars($echo, ENT_NOQUOTES)));
        } else {
            $this->invalid($player);
        }
    }
}
