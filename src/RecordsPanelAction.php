<?php
namespace Funseco\Looks;

use Funseco\Core\Models\Player;

/** Click on a records panel value: runs the matching list command as the player. */
class RecordsPanelAction
{
    public function handle(Player $player, string $action): void
    {
        $row = substr($action, strlen('records_'));
        $command = RecordsPanel::COMMANDS[$row] ?? null;
        if ($command !== null && \Chat::has($command)) {
            \Chat::run($player, $command);
        }
    }
}
