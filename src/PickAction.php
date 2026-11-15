<?php
namespace Funseco\Looks;

use Funseco\Core\Models\Player;
use Funseco\Looks\Commands\ChoiceCommand;
use Funseco\Looks\Commands\PanelCommand;
use Funseco\Looks\Commands\StyleCommand;

/** Click on a row of /style list or a panel list: select that entry. */
class PickAction
{
    public function handle(Player $player, string $action): void
    {
        $kind = state()->get('looks:list:'.$player->login)['kind'] ?? null;
        $command = match (true) {
            $kind === 'style'                                    => new StyleCommand,
            is_string($kind) && str_starts_with($kind, 'panel:') => new PanelCommand(substr($kind, 6)),
            default                                              => null,
        };

        if ($command instanceof ChoiceCommand) {
            $command->pick($player, (int) substr($action, strlen('looks_pick_')));
        }
    }
}
