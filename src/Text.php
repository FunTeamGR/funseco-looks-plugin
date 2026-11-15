<?php
namespace Funseco\Looks;

use Funseco\Core\Models\Player;

final class Text
{
    public static function get(string $key, string|int ...$values): string
    {
        $text = config("looks-plugin.messages.$key", '');
        $text = format_colors($text);   // colour tokens first: values must not expand {#..}
        $map = [];
        foreach ($values as $i => $value) {
            $map['{'.($i + 1).'}'] = (string) $value;
        }

        return strtr($text, $map);
    }

    public static function tell(Player $player, string $message): void
    {
        \Server::query()->ChatSendServerMessageToLogin($message, $player->login);
    }

    public static function broadcast(string $message): void
    {
        \Server::query()->ChatSendServerMessage($message);
    }

    /** Nickname without $w (wide) codes, as XAseco shows it in admin messages. */
    public static function nick(Player $player): string
    {
        return str_ireplace('$w', '', $player->nickname ?: $player->login);
    }
}
