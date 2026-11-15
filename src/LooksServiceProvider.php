<?php
namespace Funseco\Looks;

use Funseco\Core\SharedKernel\PublishesPluginConfig;
use Illuminate\Support\Facades\Event;
use Funseco\Core\SharedKernel\Settings\Setting;
use Funseco\Core\SharedKernel\Settings\SettingsRegistry;
use Illuminate\Support\ServiceProvider;
use Funseco\Core\Services\Looks\PlayerLooks;
use Funseco\Core\SharedKernel\Events\DedimaniaRecordsUpdated;
use Funseco\Core\SharedKernel\Events\LocalRecordsUpdated;
use Funseco\Core\SharedKernel\Events\MapEnded;
use Funseco\Core\SharedKernel\Events\MapStarted;
use Funseco\Core\SharedKernel\Events\PanelChanged;
use Funseco\Core\SharedKernel\Events\PlayerConnected;
use Funseco\Core\SharedKernel\Events\PlayerDisconnected;
use Funseco\Core\SharedKernel\Events\PlayerFinished;
use Funseco\Core\SharedKernel\Events\StartupEvent;
use Funseco\Core\SharedKernel\Events\TrackExchangeInfoLoaded;
use Funseco\Core\Services\ManiaLink\ManiaLinkService;
use Funseco\Looks\Commands as C;

class LooksServiceProvider extends ServiceProvider
{
    use PublishesPluginConfig;

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/looks-plugin.php', 'looks-plugin');
        $this->mergeConfigFrom(__DIR__.'/../config/panels.php', 'panels.records');
        $this->publishPluginConfig(__DIR__.'/..');
        $this->app->make(SettingsRegistry::class)->declare('looks', 'looks-plugin', [
            // action ids for the pick buttons are registered in boot()
            Setting::int('clickable_rows', 'Clickable rows in choice lists', min: 1, restart: true),
            Setting::int('list_max.admin', 'Admin panel list length', min: 1),
            Setting::int('list_max.donate', 'Donate panel list length', min: 1),
            Setting::int('list_max.records', 'Records panel list length', min: 1),
            Setting::int('list_max.vote', 'Vote panel list length', min: 1),
        ]);
        $this->app->singleton(RecordsPanel::class);
    }

    public function boot(): void
    {
        $max = max(1, (int) config('looks-plugin.clickable_rows', 60));
        app(ManiaLinkService::class)->addActions(array_map(fn ($n) => "looks_pick_$n", range(1, $max)), PickAction::class);

        \Chat::register(C\StyleCommand::class);
        \Chat::register(C\AdminStyleCommand::class);
        \Chat::register(C\ThemeCommand::class);
        \Chat::register(C\AdminThemeCommand::class);
        foreach (['admin', 'donate', 'records', 'vote'] as $kind) {
            \Chat::register(new C\PanelCommand($kind));
        }
        foreach (['admpanel', 'donpanel', 'recpanel', 'votepanel'] as $command) {
            \Chat::register(new C\AdminDefaultPanelCommand($command));
        }

        $this->bootRecordsPanel();
    }

    /** The records panel: values from core contracts, redrawn on the events that change them. */
    private function bootRecordsPanel(): void
    {
        $maniaLinks = app(ManiaLinkService::class);
        $maniaLinks->addAction(RecordsPanel::ID);
        $maniaLinks->addActions(RecordsPanel::actions(), RecordsPanelAction::class);
        app(PlayerLooks::class)->registerPanels('records', RecordsPanel::templates());

        $panel = fn () => app(RecordsPanel::class);
        Event::listen(StartupEvent::class, function () use ($panel) {
            $panel()->loadAllPbs();
            $panel()->showToAll();
        });
        Event::listen(MapStarted::class, function () use ($panel) {
            $panel()->setScore(false);
            $panel()->loadAllPbs();
            $panel()->showToAll();
        });
        Event::listen(MapEnded::class, function () use ($panel) {
            $panel()->setScore(true);
            $panel()->hideFromAll();
        });
        Event::listen(PlayerConnected::class, function (PlayerConnected $e) use ($panel) {
            $panel()->loadPb($e->player);
            $panel()->showTo($e->player);
        });
        Event::listen(PlayerDisconnected::class, fn (PlayerDisconnected $e) => $panel()->forget($e->player));
        Event::listen(PlayerFinished::class, function (PlayerFinished $e) use ($panel) {
            $panel()->finished($e->player, $e->score) && $panel()->showTo($e->player);
        });
        Event::listen(LocalRecordsUpdated::class, fn () => $panel()->localChanged());
        foreach ([DedimaniaRecordsUpdated::class, TrackExchangeInfoLoaded::class] as $event) {
            Event::listen($event, fn () => $panel()->showToAll());
        }
        Event::listen(PanelChanged::class, function (PanelChanged $e) use ($panel) {
            $e->kind === 'records' && $panel()->showTo($e->player);
        });
    }
}
