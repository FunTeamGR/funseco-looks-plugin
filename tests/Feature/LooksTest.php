<?php
namespace Funseco\Looks\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Funseco\Core\Testing\FakesServer;
use Funseco\Looks\Tests\TestCase;
use Funseco\Admin\AdminPanel;
use Funseco\Core\Models\Player;
use Funseco\Core\Models\PlayerExtra;
use Funseco\Core\Services\Cache\CurrentPlayers;
use Funseco\Core\Services\Chat\Facades\Chat;
use Funseco\Core\Services\Looks\PlayerLooks;
use Funseco\Core\Services\ManiaLink\Facades\ManiaLink;
use Funseco\Core\SharedKernel\UI\Window;
use Funseco\Votes\VotePanel;

class LooksTest extends TestCase
{
    use FakesServer;
    use RefreshDatabase;

    private Player $alice;
    private Player $boss;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeServer();
        CurrentPlayers::clear();
        config(['adminops.masteradmins' => ['boss']]);
        $this->alice = Player::factory()->create(['login' => 'alice', 'nickname' => 'Alice']);
        $this->boss = Player::factory()->create(['login' => 'boss', 'nickname' => 'Boss']);
        CurrentPlayers::add($this->alice);
        CurrentPlayers::add($this->boss);
    }

    private function looks(): PlayerLooks
    {
        return app(PlayerLooks::class);
    }

    private function toLogin(string $login): array
    {
        return array_values(array_map(fn ($a) => $a[0], array_filter($this->sent('ChatSendServerMessageToLogin'), fn ($a) => $a[1] === $login)));
    }

    private function click(string $action, Player $as): void
    {
        ManiaLink::dispatchAnswer($as, ManiaLink::getAction($action));
    }

    public function test_default_window_look_is_dark_blur_and_all_21_styles_exist()
    {
        $this->assertCount(21, $this->looks()->styleNames());
        Window::show($this->alice, 'Hi', [['a']]);
        $this->assertStringContainsString('style="Bgs1" substyle="BgWindow3"', $this->window());
    }

    public function test_style_selection_changes_only_that_players_windows_and_is_stored()
    {
        Chat::resolve('/style Orange', 'alice');
        $this->assertSame(['> Style Orange selected!'], array_map(fn ($m) => strip_tags(preg_replace('/\$[0-9a-f]{3}/i', '', $m)), $this->toLogin('alice')));

        Window::show($this->alice, 'Hi', [['a']]);
        $this->assertStringContainsString('style="Bgs1InRace" substyle="BgWindow1"', $this->window());
        $this->assertStringContainsString('substyle="BgTitle3_1"', $this->window());

        Window::show($this->boss, 'Hi', [['a']]);
        $this->assertStringContainsString('substyle="BgWindow3"', $this->window());

        $this->assertSame('Orange', PlayerExtra::where('player_id', $this->alice->id)->value('style'));
    }

    public function test_stored_style_survives_a_reconnect_and_off_or_unknown_means_default()
    {
        PlayerExtra::create(['player_id' => $this->alice->id, 'style' => 'GreenBlur']);
        $this->assertSame('GreenBlur', $this->looks()->styleName($this->alice));

        $this->looks()->forget('alice');
        PlayerExtra::where('player_id', $this->alice->id)->update(['style' => 'NoSuchStyle']);
        $this->assertSame('DarkBlur', $this->looks()->styleName($this->alice));

        $this->looks()->forget('alice');
        PlayerExtra::where('player_id', $this->alice->id)->update(['style' => '']);
        $this->assertSame('DarkBlur', $this->looks()->styleName($this->alice));
    }

    public function test_style_list_numbers_clicks_default_and_errors()
    {
        Chat::resolve('/style list', 'alice');
        $this->assertStringContainsString('Currently available styles:', $this->window());
        $this->assertStringContainsString('01.', $this->window());
        $this->assertStringNotContainsString('>off<', $this->window());

        Chat::resolve('/style 2', 'alice'); // BlackBlur: sorted second
        $this->assertSame('BlackBlur', $this->looks()->styleName($this->alice));

        $this->click('looks_pick_3', $this->alice); // Blue, and the list is shown again in the new look
        $this->assertSame('Blue', $this->looks()->styleName($this->alice));
        $this->assertStringContainsString('Currently available styles:', $this->window());

        Chat::resolve('/style default', 'alice');
        $this->assertSame('DarkBlur', PlayerExtra::where('player_id', $this->alice->id)->value('style'));

        $this->calls = [];
        Chat::resolve('/style nope', 'alice');
        Chat::resolve('/style', 'alice');
        $this->assertCount(2, $this->toLogin('alice'));
        $this->assertStringContainsString('No valid style file', $this->toLogin('alice')[0]);
        $this->assertStringContainsString('No style specified', $this->toLogin('alice')[1]);

        Chat::resolve('/style help', 'alice');
        $this->assertStringContainsString('will change the window style', $this->window() ?: 'style');
    }

    public function test_style_off_is_acknowledged_and_admin_style_gaps_use_the_unknown_admin_text()
    {
        Chat::resolve('/style off', 'alice');
        $this->assertStringContainsString('TMF window style disabled!', $this->toLogin('alice')[0]);
        $this->assertSame('DarkBlur', $this->looks()->styleName($this->alice));

        Chat::resolve('/admin style', 'boss');
        Chat::resolve('/admin donpanel', 'boss');
        $this->assertStringContainsString('Unknown admin command or missing parameter(s)', $this->toLogin('boss')[0]);
        $this->assertStringContainsString('Unknown admin command or missing parameter(s)', $this->toLogin('boss')[1]);

        Chat::resolve('/admin style Gray', 'boss');
        Chat::resolve('/admin style off', 'boss');
        $this->assertSame('DarkBlur', $this->looks()->defaultStyle());
        $this->assertStringContainsString('reset default window style', $this->messages('ChatSendServerMessage')[1]);
    }

    public function test_admin_style_changes_the_default_for_players_without_choice_and_needs_the_ability()
    {
        Chat::resolve('/admin style Gray', 'alice');
        $this->assertSame('DarkBlur', $this->looks()->defaultStyle());

        Chat::resolve('/admin style Nope', 'boss');
        $this->assertSame('DarkBlur', $this->looks()->defaultStyle());

        Chat::resolve('/admin style Gray', 'boss');
        $this->assertSame('Gray', $this->looks()->defaultStyle());
        $this->assertSame('Gray', $this->looks()->styleName($this->alice));
        $this->assertCount(1, $this->messages('ChatSendServerMessage'));
    }

    public function test_vote_panel_follows_the_players_template_and_can_be_off()
    {
        $this->calls = [];
        app(VotePanel::class)->show($this->alice, 'Yes', 'No');
        $xml = $this->sent('SendDisplayManialinkPageToLogin')[0][1];
        $this->assertStringContainsString('<frame posn="-56.8 -45.6 0"><quad sizen="10 2.5"', $xml);
        $this->assertStringContainsString('actionkey="1"', $xml);
        $this->assertStringContainsString('actionkey="2"', $xml);
        $this->assertNotFalse(simplexml_load_string($xml));

        $this->calls = [];
        Chat::resolve('/votepanel TopCenter', 'alice');
        $this->assertSame('VoteTopCenter', $this->looks()->panel($this->alice, 'vote'));
        $this->assertStringContainsString('<frame posn="-5.8 40 0">', $this->window(), 'two-second preview');

        Chat::resolve('/votepanel off', 'alice');
        $this->assertSame('', $this->looks()->panel($this->alice, 'vote'));
        $this->calls = [];
        app(VotePanel::class)->show($this->alice, 'Yes', 'No');
        $this->assertSame([], $this->sent('SendDisplayManialinkPageToLogin'));
    }

    public function test_panel_choices_are_stored_as_four_names_and_numbers_pick_from_the_list()
    {
        Chat::resolve('/votepanel off', 'alice');
        $this->assertSame('AdminBelowChat/DonateBelowCPListRM/RecordsRightBottom/', PlayerExtra::where('player_id', $this->alice->id)->value('panels'));

        Chat::resolve('/donpanel list', 'alice');
        $this->assertStringContainsString('Currently available donate panels:', $this->window());
        Chat::resolve('/donpanel 1', 'alice'); // sorted: BelowCPList first
        $this->assertSame('DonateBelowCPList', $this->looks()->panel($this->alice, 'donate'));
        $this->assertSame('AdminBelowChat/DonateBelowCPList/RecordsRightBottom/', PlayerExtra::where('player_id', $this->alice->id)->value('panels'));

        $this->looks()->forget('alice');
        $this->assertSame('', $this->looks()->panel($this->alice, 'vote'), 'off persists');
        $this->assertSame('DonateBelowCPList', $this->looks()->panel($this->alice, 'donate'));

        Chat::resolve('/donpanel default', 'alice');
        $this->assertSame('DonateBelowCPListRM', $this->looks()->panel($this->alice, 'donate'));
        Chat::resolve('/donpanel nope', 'alice');
        $this->assertStringContainsString('No valid donate panel file', last($this->toLogin('alice')));
    }

    public function test_admin_panel_choice_is_staff_only_and_redraws_the_panel()
    {
        Chat::resolve('/admin panel AboveChat', 'alice');
        $this->assertSame('AdminBelowChat', $this->looks()->panel($this->alice, 'admin'));

        $this->calls = [];
        Chat::resolve('/admin panel AboveChat', 'boss');
        $this->assertStringContainsString('<frame posn="-56 -29.2 0"><quad sizen="16 3.5"', $this->window());

        Chat::resolve('/admin panel off', 'boss');
        $this->assertSame('<manialink id="'.ManiaLink::getAction('admin_panel').'"></manialink>', $this->window());
        $this->assertNotNull(AdminPanel::xml('AdminLeftEdge'));
    }

    public function test_admin_default_panels_change_the_server_default_only_for_masteradmins()
    {
        Chat::resolve('/admin votepanel TopCenter', 'alice');
        $this->assertSame('VoteBelowChat', $this->looks()->defaultPanel('vote'));

        Chat::resolve('/admin votepanel TopCenter', 'boss');
        $this->assertSame('VoteTopCenter', $this->looks()->defaultPanel('vote'));
        $this->assertSame('VoteTopCenter', $this->looks()->panel($this->alice, 'vote'));
        $this->assertCount(1, $this->messages('ChatSendServerMessage'));

        Chat::resolve('/admin donpanel off', 'boss');
        $this->assertSame('', $this->looks()->defaultPanel('donate'));

        $this->calls = [];
        Chat::resolve('/admin admpanel nope', 'boss');
        Chat::resolve('/admin admpanel AboveChat', 'boss');
        $this->assertSame([], $this->messages('ChatSendServerMessage'), 'admpanel talks to the caller only');
        $this->assertCount(2, $this->toLogin('boss'));
    }
}
