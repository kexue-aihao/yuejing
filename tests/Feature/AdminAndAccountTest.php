<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\AuditLog;
use App\Models\Novel;
use App\Models\Submission;
use App\Models\User;
use App\Services\EnvironmentConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Mockery;
use Tests\Feature\Concerns\CreatesYuejingData;
use Tests\TestCase;

class AdminAndAccountTest extends TestCase
{
    use CreatesYuejingData;
    use RefreshDatabase;

    public function test_admin_pages_and_api_require_admin_role(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->assertViewIs('pages.admin.dashboard');
        $this->actingAs($admin)->getJson('/api/admin')->assertOk()->assertJsonStructure(['users', 'novels', 'chapters', 'pending_submissions']);
    }

    public function test_admin_settings_explains_environment_configuration_and_respects_env_email_gate(): void
    {
        Config::set('yuejing.email_verification.required', false);
        $admin = User::factory()->create(['role' => 'admin']);
        \App\Models\Setting::create([
            'key' => 'email_verification_required',
            'value' => '1',
            'type' => 'boolean',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.settings'));

        $response->assertOk()
            ->assertViewIs('pages.admin.settings')
            ->assertSee(__('ui.admin.environment_config'))
            ->assertSee(__('ui.admin.env_config_descriptions.email_verification'))
            ->assertSee('APP_ENV')
            ->assertSee('name="environment[APP_NAME]"', false)
            ->assertSee('name="environment[APP_KEY]"', false)
            ->assertSee('disabled', false);
        $this->assertFalse($response->viewData('settingValues')['email_verification_required']);
        $this->assertFalse($response->viewData('environmentConfig')['email_verification_enabled']);
    }

    public function test_admin_can_submit_environment_configuration_without_writing_the_real_env_file(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $environment = Mockery::mock(EnvironmentConfigService::class);
        $environment->shouldReceive('update')->once()->with([
            'APP_NAME' => 'Updated Yuejing',
            'SESSION_ENCRYPT' => false,
        ]);
        $this->app->instance(EnvironmentConfigService::class, $environment);

        $this->from(route('admin.settings'))
            ->actingAs($admin)
            ->put(route('admin.settings.update'), [
                'environment' => [
                    'APP_NAME' => 'Updated Yuejing',
                    'SESSION_ENCRYPT' => '0',
                ],
            ])
            ->assertRedirect(route('admin.settings'))
            ->assertSessionHas('status', __('ui.messages.environment_config_updated'));
    }

    public function test_admin_must_provide_a_category_slug(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->from(route('admin.categories.index'))
            ->actingAs($admin)
            ->postWithCsrf(route('admin.categories.store'), [
                'name' => '校园',
                'slug' => '',
            ])
            ->assertRedirect(route('admin.categories.index'))
            ->assertSessionHasErrors('slug');

        $this->assertDatabaseMissing('categories', ['name' => '校园']);
    }

    public function test_admin_can_create_a_category_with_a_pinyin_slug(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->from(route('admin.categories.index'))
            ->actingAs($admin)
            ->postWithCsrf(route('admin.categories.store'), [
                'name' => '校园',
                'slug' => 'xiaoyuan',
            ])
            ->assertRedirect(route('admin.categories.index'));

        $this->assertDatabaseHas('categories', [
            'name' => '校园',
            'slug' => 'xiaoyuan',
        ]);
    }

    public function test_approved_submission_creates_published_novel_and_first_chapter(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $author = User::factory()->create(['role' => 'user']);
        $category = Category::create(['name' => '都市', 'slug' => 'dushi-admin-test']);
        $submission = Submission::create([
            'cover_url' => 'https://example.test/covers/approved.jpg',
            'user_id' => $author->id,
            'category_id' => $category->id,
            'title' => '审核后上架的故事',
            'synopsis' => '简介',
            'manuscript' => "第一段正文。\n第二段正文。",
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->putJsonWithCsrf('/api/admin/submissions/'.$submission->id.'/review', [
                'status' => 'approved',
                'review_note' => '内容完整。',
            ])
            ->assertOk();

        $novel = Novel::where('title', '审核后上架的故事')->firstOrFail();
        $this->assertSame('published', $novel->status);
        $this->assertSame('https://example.test/covers/approved.jpg', $novel->cover_url);
        $this->assertNotNull($novel->published_at);
        $this->assertSame($novel->id, $submission->fresh()->novel_id);
        $this->assertDatabaseHas('chapters', ['novel_id' => $novel->id, 'chapter_number' => 1, 'status' => 'published']);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'submission.approved',
            'auditable_type' => Submission::class,
            'auditable_id' => $submission->id,
            'ip_address' => '127.0.0.1',
        ]);
    }

    public function test_submission_audit_page_only_shows_submission_events(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $author = User::factory()->create();
        $submission = Submission::create([
            'user_id' => $author->id,
            'title' => '投稿审计展示',
            'manuscript' => '正文',
            'status' => 'pending',
        ]);
        AuditLog::create(['user_id' => $admin->id, 'action' => 'auth.logged_in', 'metadata' => [], 'ip_address' => '10.0.0.1']);
        AuditLog::create(['user_id' => $admin->id, 'action' => 'submission.created', 'auditable_type' => Submission::class, 'auditable_id' => $submission->id, 'metadata' => ['title' => $submission->title], 'ip_address' => '127.0.0.1']);

        $this->actingAs($admin)
            ->get(route('admin.audit-logs.index'))
            ->assertOk()
            ->assertSee('投稿审计日志')
            ->assertSee('投稿审计展示')
            ->assertDontSee('auth.logged_in');

        $this->actingAs($admin)
            ->getJson('/api/admin/audit-logs')
            ->assertOk()
            ->assertJsonPath('data.0.action', 'submission.created');
    }

    public function test_rejected_submission_writes_audit_and_does_not_create_novel(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $author = User::factory()->create();
        $submission = Submission::create([
            'user_id' => $author->id,
            'title' => '被拒绝的投稿',
            'manuscript' => '正文',
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->putJsonWithCsrf('/api/admin/submissions/'.$submission->id.'/review', [
                'status' => 'rejected',
                'review_note' => '请补充完整的章节内容。',
            ])
            ->assertOk();

        $this->assertDatabaseMissing('novels', ['title' => '被拒绝的投稿']);
        $audit = AuditLog::where('action', 'submission.rejected')->where('auditable_id', $submission->id)->firstOrFail();
        $this->assertSame('rejected', $audit->metadata['status']);
        $this->assertSame('请补充完整的章节内容。', $audit->metadata['review_note']);
        $this->assertSame($admin->id, $audit->metadata['reviewer_id']);
    }

    public function test_already_reviewed_submission_cannot_be_reviewed_again(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $author = User::factory()->create();
        $submission = Submission::create([
            'user_id' => $author->id,
            'title' => '不能重复审核',
            'manuscript' => '正文',
            'status' => 'pending',
        ]);

        $this->actingAs($admin)->putJsonWithCsrf('/api/admin/submissions/'.$submission->id.'/review', ['status' => 'rejected'])->assertOk();
        $this->actingAs($admin)->putJsonWithCsrf('/api/admin/submissions/'.$submission->id.'/review', ['status' => 'approved'])->assertStatus(409);
        $this->assertSame(1, AuditLog::where('auditable_id', $submission->id)->where('action', 'like', 'submission.%')->count());
    }

    public function test_public_layout_shows_current_request_ip(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->get(route('home'))
            ->assertOk()
            ->assertSee('203.0.113.10');
    }

    public function test_public_layout_ignores_forwarded_ip_from_untrusted_proxy(): void
    {
        Config::set('trustedproxy.proxies', null);

        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeader('X-Forwarded-For', '198.51.100.42')
            ->get(route('home'))
            ->assertOk()
            ->assertSee('127.0.0.1')
            ->assertDontSee('198.51.100.42');
    }

    public function test_public_layout_uses_forwarded_ip_from_configured_proxy(): void
    {
        Config::set('trustedproxy.proxies', ['127.0.0.1']);

        try {
            $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
                ->withHeader('X-Forwarded-For', '198.51.100.42')
                ->get(route('home'))
                ->assertOk()
                ->assertSee('198.51.100.42');
        } finally {
            Config::set('trustedproxy.proxies', null);
        }
    }

    public function test_account_pages_render_and_two_factor_entry_is_available(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('account.settings'))->assertOk()->assertSee('二步验证');
        $this->actingAs($user)->get(route('account.favorites'))->assertOk()->assertViewIs('pages.account.favorites');
        $this->actingAs($user)->get(route('account.reading-records'))->assertOk()->assertViewIs('pages.account.reading-records');
    }
}
