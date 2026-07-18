<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunicationPageContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_groups_page_exposes_parseable_api_configuration(): void
    {
        $response = $this->actingAs(User::factory()->create())->get(route('groups.page'));
        $response->assertOk();
        $config = $this->extractApiConfig($response->getContent());

        $this->assertSame([
            'users',
            'index',
            'store',
            'show',
            'addMember',
            'removeMember',
            'sendMessage',
            'read',
            'stream',
        ], array_keys($config));
        $this->assertSame('/api/groups', parse_url($config['index'], PHP_URL_PATH));
        $this->assertSame('/api/groups', parse_url($config['store'], PHP_URL_PATH));
    }

    public function test_messages_page_exposes_parseable_api_configuration(): void
    {
        $response = $this->actingAs(User::factory()->create())->get(route('messages.page'));
        $response->assertOk();
        $config = $this->extractApiConfig($response->getContent());

        $this->assertSame([
            'users',
            'index',
            'store',
            'show',
            'read',
            'stream',
        ], array_keys($config));
        $this->assertSame('/api/messages/users', parse_url($config['users'], PHP_URL_PATH));
        $this->assertSame('/api/messages', parse_url($config['index'], PHP_URL_PATH));
    }

    public function test_dashboard_embeds_private_messages_and_keeps_personal_center_navigation(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->get(route('dashboard', ['section' => 'messages']));

        $response->assertOk()
            ->assertViewIs('pages.dashboard')
            ->assertSee('data-messages-app', false)
            ->assertSee('embedded-communication-page', false)
            ->assertSee('<nav class="dashboard-nav"', false)
            ->assertSee('href="'.route('dashboard', ['section' => 'messages']).'"', false);
        $this->assertMatchesRegularExpression('/<details class="dashboard-nav-group"[^>]*open/', $response->getContent());
    }

    public function test_dashboard_embeds_groups_and_keeps_personal_center_navigation(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->get(route('dashboard', ['section' => 'groups']));

        $response->assertOk()
            ->assertViewIs('pages.dashboard')
            ->assertSee('data-groups-app', false)
            ->assertSee('embedded-communication-page', false)
            ->assertSee('<nav class="dashboard-nav"', false)
            ->assertSee('href="'.route('dashboard', ['section' => 'groups']).'"', false);
        $this->assertMatchesRegularExpression('/<details class="dashboard-nav-group"[^>]*open/', $response->getContent());
    }

    private function extractApiConfig(string $html): array
    {
        $this->assertSame(1, preg_match("~data-api='([^']+)'~", $html, $matches));

        return json_decode(html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8'), true, 512, JSON_THROW_ON_ERROR);
    }
}
