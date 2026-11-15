<?php
namespace Funseco\Looks\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Funseco\Core\Testing\FakesServer;
use Funseco\Looks\Tests\TestCase;
use Funseco\Core\Models\Map;
use Funseco\Core\Models\Player;
use Funseco\Core\Models\PlayerExtra;
use Funseco\Core\Servers\TmForever\Values\CheckpointCollection;
use Funseco\Core\Services\Cache\CurrentMap;
use Funseco\Core\Services\Cache\CurrentPlayers;
use Funseco\Core\Services\Cache\ServerInfo;
use Funseco\Core\Services\Chat\Facades\Chat;
use Funseco\Core\Services\Looks\PlayerLooks;
use Funseco\Core\Services\ManiaLink\Facades\ManiaLink;
use Funseco\Core\SharedKernel\CurrentDedimaniaRecords;
use Funseco\Core\SharedKernel\CurrentLocalRecords;
use Funseco\Core\SharedKernel\FinishHistory;
use Funseco\Core\SharedKernel\Values\Record;
use Funseco\Core\SharedKernel\Values\RecordCollection;
use Funseco\Looks\RecordsPanel;

class RecordsPanelTest extends TestCase
{
    use FakesServer;
    use RefreshDatabase;

    private Player $alice;
    private Map $map;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeServer();
        state()->flush();
        app(PlayerLooks::class)->registerPanels('records', RecordsPanel::templates());   // the flush forgot the boot-time catalog
        ServerInfo::set(['server_name' => 'Test']);
        CurrentPlayers::clear();
        config(['adminops.masteradmins' => ['boss']]);
        $this->alice = Player::factory()->create(['login' => 'alice', 'nickname' => 'Alice']);
        $boss = Player::factory()->create(['login' => 'boss', 'nickname' => 'Boss']);
        CurrentPlayers::add($this->alice);
        CurrentPlayers::add($boss);
        $this->map = Map::factory()->create(['uid' => 'u1', 'name' => 'Speed']);
        CurrentMap::set($this->map);
        ServerInfo::setGameMode(1);
    }

    private function records(string $contract, array $records): void
    {
        $this->app->instance($contract, new class(new RecordCollection($records)) implements CurrentLocalRecords, CurrentDedimaniaRecords {
            public function __construct(private RecordCollection $records)
            {
            }

            public function records(): RecordCollection
            {
                return $this->records;
            }
        });
    }

    private function panelXml(string $login = 'alice'): string
    {
        $xml = array_values(array_filter($this->sent('SendDisplayManialinkPageToLogin'), fn ($a) => $a[0] === $login));

        return (string) (end($xml)[1] ?? '');
    }

    public function test_default_template_shows_the_four_values_with_their_click_targets(): void
    {
        $this->records(CurrentLocalRecords::class, [new Record($this->alice->id, 31234)]);
        $this->records(CurrentDedimaniaRecords::class, [new Record($this->alice->id, 30456)]);
        $panel = app(RecordsPanel::class);
        $panel->loadPb($this->alice);

        $xml = $panel->xml('RecordsRightBottom', $panel->values($this->alice));

        $this->assertNotFalse(simplexml_load_string($xml));
        $this->assertStringContainsString('<frame posn="53.5 -32.7 0">', $xml);
        foreach (['PB:', 'Local:', 'Dedi:', 'TMX:'] as $label) {
            $this->assertStringContainsString('text="'.$label.'"', $xml);
        }
        $this->assertStringContainsString('text="0:31.23"', $xml);          // PB (ranked local record) and Local #1
        $this->assertStringContainsString('text="0:30.45"', $xml);          // Dedi #1
        $this->assertStringContainsString('text="   --.--"', $xml);         // no TMX record
        foreach (['pb', 'local', 'dedi', 'tmx'] as $row) {
            $this->assertStringContainsString('action="'.ManiaLink::getAction('records_'.$row).'"', $xml);
        }
    }

    public function test_every_template_is_valid_xml_and_unknown_or_off_draws_nothing(): void
    {
        $panel = app(RecordsPanel::class);
        $values = $panel->values($this->alice);
        $this->assertCount(16, RecordsPanel::templates());
        foreach (RecordsPanel::templates() as $name) {
            $this->assertNotFalse(simplexml_load_string($panel->xml($name, $values)), $name);
        }
        $this->assertNull($panel->xml('', $values));
        $this->assertNull($panel->xml('RecordsNope', $values));
        $this->assertSame(substr_count($panel->xml('RecordsRightBottomNoDedi', $values), 'action='), 3, 'NoDedi has three rows');
    }

    public function test_stunts_format_scores_and_skip_dedimania(): void
    {
        ServerInfo::setGameMode(4);
        $this->records(CurrentLocalRecords::class, [new Record($this->alice->id, 420)]);
        $this->records(CurrentDedimaniaRecords::class, [new Record($this->alice->id, 999)]);
        $values = app(RecordsPanel::class)->values($this->alice);

        $this->assertSame('  420', $values['local']);
        $this->assertSame('  ---', $values['dedi']);
        $this->assertSame('  ---', $values['pb']);
    }

    public function test_pb_falls_back_to_the_stored_finishes_and_improves_on_finish(): void
    {
        $this->app->instance(FinishHistory::class, new class($this->map->id) implements FinishHistory {
            public function __construct(private int $mapId)
            {
            }

            public function finishedMapIds(Player $player): array { return [$this->mapId]; }
            public function bestScores(Player $player): array { return [$this->mapId => 45000]; }
            public function finishCount(Player $player, Map $map): int { return 1; }
            public function averageScore(Player $player, Map $map, int $last): ?int { return null; }
        });
        $panel = app(RecordsPanel::class);
        $panel->loadPb($this->alice);
        $this->assertSame(45000, $panel->pb($this->alice));

        $this->assertFalse($panel->finished($this->alice, 46000));
        $this->assertTrue($panel->finished($this->alice, 44000));
        $this->assertSame(44000, $panel->pb($this->alice));
    }

    public function test_shown_at_map_start_hidden_at_score_and_chosen_with_recpanel(): void
    {
        $panel = app(RecordsPanel::class);
        // the listeners' work (full MapStarted/MapEnded events would also run Dedimania's HTTP fetch)
        $panel->setScore(false);
        $panel->loadAllPbs();
        $panel->showToAll();
        $this->assertStringContainsString('<frame posn="53.5 -32.7 0">', $this->panelXml());

        $this->calls = [];
        $panel->setScore(true);
        $panel->hideFromAll();
        $panel->showTo($this->alice);   // nothing is drawn at the score screen
        $this->assertSame([], $this->sent('SendDisplayManialinkPageToLogin'));
        $this->assertStringContainsString('<manialink id="'.ManiaLink::getAction('records_panel').'"></manialink>', implode('', array_map(fn ($a) => $a[0], $this->sent('SendDisplayManialinkPage'))));

        $panel->setScore(false);
        $this->calls = [];
        Chat::resolve('/recpanel LeftSize2', 'alice');
        $this->assertSame('RecordsLeftSize2', app(PlayerLooks::class)->panel($this->alice, 'records'));
        $this->assertStringContainsString('<frame posn="-64.8 23.3 0">', $this->panelXml());

        Chat::resolve('/recpanel off', 'alice');
        $this->assertStringContainsString('></manialink>', $this->panelXml());
        $this->assertStringNotContainsString('<frame', $this->panelXml());
    }

    public function test_admin_recpanel_changes_the_server_default_for_masteradmins_only(): void
    {
        Chat::resolve('/admin recpanel LeftSize1', 'alice');
        $this->assertSame('RecordsRightBottom', app(PlayerLooks::class)->defaultPanel('records'));

        Chat::resolve('/admin recpanel LeftSize1', 'boss');
        $this->assertSame('RecordsLeftSize1', app(PlayerLooks::class)->defaultPanel('records'));
    }

    public function test_a_value_click_runs_the_matching_list_command(): void
    {
        $ran = [];
        Chat::register(new class($ran) implements \Funseco\Core\SharedKernel\Commands\Command {
            public function __construct(private array &$ran)
            {
            }

            public function name(): string { return 'topsums'; }
            public function handle(Player $player, array $args): void { $this->ran[] = $player->login; }
        });

        $this->assertSame('pb', \Funseco\Looks\RecordsPanel::COMMANDS['pb']);
        $this->assertTrue(Chat::has('pb'), 'the pb row must point at a live command');
        $this->assertTrue(Chat::has(\Funseco\Looks\RecordsPanel::COMMANDS['local']));

        ManiaLink::dispatchAnswer($this->alice, ManiaLink::getAction('records_pb'));
        $this->assertSame([], $ran, 'the dead topsums command is no longer what the pb row runs');
    }
}
