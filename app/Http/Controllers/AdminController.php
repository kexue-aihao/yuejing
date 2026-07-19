<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Chapter;
use App\Models\Novel;
use App\Models\Setting;
use App\Models\Submission;
use App\Models\User;
use App\Services\AppSettingService;
use App\Services\MarkdownRenderer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class AdminController extends Controller
{
    public function dashboard(Request $request)
    {
        $data = [
            'users' => User::count(),
            'novels' => Novel::count(),
            'chapters' => Chapter::count(),
            'pending_submissions' => Submission::where('status', 'pending')->count(),
        ];

        if (! $this->wantsJson($request)) {
            $data['recent_submissions'] = Submission::with('user:id,name')->latest()->limit(5)->get();
            $data['recent_novels'] = Novel::with('author:id,name')->latest()->limit(5)->get();

            return view('pages.admin.dashboard', $data);
        }

        return response()->json($data);
    }

    public function settings(Request $request, AppSettingService $service)
    {
        $settings = Setting::query()->orderBy('key')->get();

        if (! $this->wantsJson($request)) {
            $settingValues = collect([
                'email_verification_required' => $service->emailVerificationRequired(),
                'site_name' => $service->get('site_name', __('ui.app.name')),
                'site_tagline' => $service->get('site_tagline', __('ui.messages.site_tagline')),
                'contact_email' => $service->get('contact_email', 'hello@yuejing.local'),
                'accent_color' => $service->get('accent_color', 'coral'),
                'show_rank' => $service->get('show_rank', true),
                'show_new' => $service->get('show_new', true),
                'allow_comments' => $service->get('allow_comments', true),
            ]);

            $environmentConfig = [
                'email_verification_enabled' => (bool) config('yuejing.email_verification.required', false),
                'items' => $this->environmentConfigItems(),
            ];

            return view('pages.admin.settings', compact('settings', 'settingValues', 'environmentConfig'));
        }

        return response()->json(['settings' => $settings]);
    }

    public function updateSettings(Request $request, AppSettingService $service)
    {
        $data = $request->validate([
            'email_verification_required' => ['sometimes', 'boolean'],
            'site_name' => ['sometimes', 'string', 'max:255'],
            'site_tagline' => ['sometimes', 'string', 'max:255'],
            'contact_email' => ['sometimes', 'email', 'max:255'],
            'accent_color' => ['sometimes', 'string', 'max:50'],
            'show_rank' => ['sometimes', 'boolean'],
            'show_new' => ['sometimes', 'boolean'],
            'allow_comments' => ['sometimes', 'boolean'],
        ]);

        foreach ($data as $key => $value) {
            $service->set($key, $value, $request->user()->id);
        }

        if (! $this->wantsJson($request)) {
            return back()->with('status', __('ui.messages.settings_updated'));
        }

        return response()->json(['message' => __('ui.messages.settings_updated')]);
    }

    private function environmentConfigItems(): array
    {
        $databaseConnection = (string) config('database.default');
        $database = (array) config("database.connections.{$databaseConnection}", []);
        $mailer = (string) config('mail.default');
        $mail = (array) config("mail.mailers.{$mailer}", []);
        $mask = static fn (mixed $value): string => filled($value)
            ? __('ui.admin.configured')
            : __('ui.admin.not_configured');
        $boolean = static fn (mixed $value): string => filter_var($value, FILTER_VALIDATE_BOOLEAN)
            ? __('ui.admin.enabled')
            : __('ui.admin.disabled');
        $value = static fn (mixed $value): string => is_scalar($value) && (string) $value !== ''
            ? (string) $value
            : __('ui.admin.not_configured');

        return [
            ['key' => 'APP_NAME', 'value' => $value(config('app.name')), 'description' => __('ui.admin.env_config_descriptions.app_name')],
            ['key' => 'APP_ENV', 'value' => $value(config('app.env')), 'description' => __('ui.admin.env_config_descriptions.app_env')],
            ['key' => 'APP_URL', 'value' => $value(config('app.url')), 'description' => __('ui.admin.env_config_descriptions.app_url')],
            ['key' => 'APP_KEY', 'value' => $mask(config('app.key')), 'description' => __('ui.admin.env_config_descriptions.app_key')],
            ['key' => 'APP_LOCALE', 'value' => $value(config('app.locale')), 'description' => __('ui.admin.env_config_descriptions.app_locale')],
            ['key' => 'APP_FALLBACK_LOCALE', 'value' => $value(config('app.fallback_locale')), 'description' => __('ui.admin.env_config_descriptions.app_fallback_locale')],
            ['key' => 'DB_CONNECTION', 'value' => $value($databaseConnection), 'description' => __('ui.admin.env_config_descriptions.db_connection')],
            ['key' => 'DB_HOST', 'value' => $value($database['host'] ?? null), 'description' => __('ui.admin.env_config_descriptions.db_host')],
            ['key' => 'DB_DATABASE', 'value' => $value($database['database'] ?? null), 'description' => __('ui.admin.env_config_descriptions.db_database')],
            ['key' => 'DB_USERNAME', 'value' => $value($database['username'] ?? null), 'description' => __('ui.admin.env_config_descriptions.db_username')],
            ['key' => 'DB_PASSWORD', 'value' => $mask($database['password'] ?? null), 'description' => __('ui.admin.env_config_descriptions.db_password')],
            ['key' => 'SESSION_DRIVER', 'value' => $value(config('session.driver')), 'description' => __('ui.admin.env_config_descriptions.session_driver')],
            ['key' => 'SESSION_LIFETIME', 'value' => $value(config('session.lifetime')), 'description' => __('ui.admin.env_config_descriptions.session_lifetime')],
            ['key' => 'SESSION_ENCRYPT', 'value' => $boolean(config('session.encrypt')), 'description' => __('ui.admin.env_config_descriptions.session_encrypt')],
            ['key' => 'CACHE_STORE', 'value' => $value(config('cache.default')), 'description' => __('ui.admin.env_config_descriptions.cache_store')],
            ['key' => 'QUEUE_CONNECTION', 'value' => $value(config('queue.default')), 'description' => __('ui.admin.env_config_descriptions.queue_connection')],
            ['key' => 'FILESYSTEM_DISK', 'value' => $value(config('filesystems.default')), 'description' => __('ui.admin.env_config_descriptions.filesystem_disk')],
            ['key' => 'MAIL_MAILER', 'value' => $value($mailer), 'description' => __('ui.admin.env_config_descriptions.mail_mailer')],
            ['key' => 'MAIL_HOST', 'value' => $value($mail['host'] ?? null), 'description' => __('ui.admin.env_config_descriptions.mail_host')],
            ['key' => 'MAIL_USERNAME', 'value' => $mask($mail['username'] ?? null), 'description' => __('ui.admin.env_config_descriptions.mail_username')],
            ['key' => 'MAIL_PASSWORD', 'value' => $mask($mail['password'] ?? null), 'description' => __('ui.admin.env_config_descriptions.mail_password')],
            ['key' => 'MAIL_FROM_ADDRESS', 'value' => $value(config('mail.from.address')), 'description' => __('ui.admin.env_config_descriptions.mail_from_address')],
            ['key' => 'YUEJING_EMAIL_VERIFICATION_REQUIRED', 'value' => $boolean(config('yuejing.email_verification.required')), 'description' => __('ui.admin.env_config_descriptions.email_verification')],
            ['key' => 'YUEJING_PAGINATION', 'value' => $value(config('yuejing.pagination')), 'description' => __('ui.admin.env_config_descriptions.pagination')],
            ['key' => 'YUEJING_TOTP_PERIOD', 'value' => $value(config('yuejing.two_factor.totp_period')), 'description' => __('ui.admin.env_config_descriptions.totp_period')],
            ['key' => 'YUEJING_TOTP_WINDOW', 'value' => $value(config('yuejing.two_factor.totp_window')), 'description' => __('ui.admin.env_config_descriptions.totp_window')],
            ['key' => 'YUEJING_TOTP_CHALLENGE_LIFETIME', 'value' => $value(config('yuejing.two_factor.challenge_lifetime')), 'description' => __('ui.admin.env_config_descriptions.totp_lifetime')],
            ['key' => 'YUEJING_TOTP_MAX_ATTEMPTS', 'value' => $value(config('yuejing.two_factor.max_attempts')), 'description' => __('ui.admin.env_config_descriptions.totp_attempts')],
        ];
    }

    public function testEmail(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);

        try {
            Mail::raw(__('ui.messages.smtp_body'), function ($message) use ($data): void {
                $message->to($data['email'])->subject(__('ui.messages.smtp_subject'));
            });
        } catch (Throwable $exception) {
            Log::warning('SMTP test failed.', ['exception' => $exception]);

            if (! $this->wantsJson($request)) {
                return back()->withErrors(['email' => __('ui.messages.smtp_test_failed')])->withInput();
            }

            return response()->json([
                'message' => __('ui.messages.smtp_test_failed'),
                'success' => false,
            ], 422);
        }

        if (! $this->wantsJson($request)) {
            return back()->with('status', __('ui.messages.smtp_test_sent'));
        }

        return response()->json(['message' => __('ui.messages.smtp_test_sent'), 'success' => true]);
    }

    public function categories(Request $request)
    {
        $categories = Category::withCount('novels')->latest()->paginate(config('yuejing.pagination'));

        if (! $this->wantsJson($request)) {
            return view('pages.admin.categories', compact('categories'));
        }

        return response()->json($categories);
    }

    public function storeCategory(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'unique:categories,slug'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $category = Category::create($data);

        if (! $this->wantsJson($request)) {
            return back()->with('status', __('ui.messages.category_created'));
        }

        return response()->json($category, 201);
    }

    public function updateCategory(Request $request, Category $category)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'slug' => ['sometimes', 'required', 'string', 'max:100', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'unique:categories,slug,'.$category->id],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $category->update($data);

        if (! $this->wantsJson($request)) {
            return back()->with('status', __('ui.messages.category_updated'));
        }

        return response()->json($category);
    }

    public function destroyCategory(Category $category)
    {
        $category->delete();

        if (! $this->wantsJson(request())) {
            return back()->with('status', __('ui.messages.category_deleted'));
        }

        return response()->json(['message' => __('ui.messages.category_deleted')]);
    }

    public function novels(Request $request)
    {
        $novels = Novel::with(['author:id,name', 'categories'])->withCount('chapters')->latest()->paginate(config('yuejing.pagination'));

        if (! $this->wantsJson($request)) {
            return view('pages.admin.novels', compact('novels'));
        }

        return response()->json($novels);
    }

    public function storeNovel(Request $request)
    {
        $data = $this->validateNovel($request);
        $categoryIds = $data['category_ids'] ?? [];
        unset($data['category_ids']);
        $data['slug'] ??= Str::slug($data['title']).'-'.Str::lower(Str::random(5));
        $data = $this->setNovelPublicationTimestamp($data);
        $novel = Novel::create($data);
        $novel->categories()->sync($categoryIds);

        if (! $this->wantsJson($request)) {
            return back()->with('status', __('ui.messages.novel_created'));
        }

        return response()->json($novel->load('categories'), 201);
    }

    public function updateNovel(Request $request, Novel $novel)
    {
        $data = $this->validateNovel($request, $novel);
        $categoryIds = $data['category_ids'] ?? null;
        unset($data['category_ids']);
        $novel->update($this->setNovelPublicationTimestamp($data, $novel));
        if ($categoryIds !== null) {
            $novel->categories()->sync($categoryIds);
        }

        if (! $this->wantsJson($request)) {
            return back()->with('status', __('ui.messages.novel_updated'));
        }

        return response()->json($novel->load('categories'));
    }

    public function destroyNovel(Novel $novel)
    {
        $novel->delete();

        if (! $this->wantsJson(request())) {
            return back()->with('status', __('ui.messages.novel_deleted'));
        }

        return response()->json(['message' => __('ui.messages.novel_deleted')]);
    }

    public function submissions(Request $request, MarkdownRenderer $markdownRenderer)
    {
        $query = Submission::with(['user:id,name,email', 'novel:id,title', 'category:id,name'])->latest();
        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        $submissions = $query->paginate(config('yuejing.pagination'))->withQueryString();
        $submissions->getCollection()->each(function (Submission $submission) use ($markdownRenderer): void {
            $submission->setAttribute('manuscript_html', $markdownRenderer->render($submission->manuscript, $submission->manuscript_format ?? 'markdown'));
        });

        if (! $this->wantsJson($request)) {
            return view('pages.admin.submissions', compact('submissions'));
        }

        return response()->json($submissions);
    }

    public function reviewSubmission(Request $request, Submission $submission)
    {
        $data = $request->validate([
            'status' => ['required', 'in:approved,rejected'],
            'review_note' => ['nullable', 'string', 'max:5000'],
        ]);

        $reviewedAt = now();
        $reviewerId = $request->user()->id;
        $submission = DB::transaction(function () use ($data, $submission, $reviewedAt, $reviewerId, $request): Submission {
            $submission = Submission::query()->lockForUpdate()->findOrFail($submission->id);
            abort_if($submission->status !== 'pending', 409, __('ui.messages.submission_processed'));

            $novel = null;
            if ($data['status'] === 'approved') {
                $novel = $submission->novel_id
                    ? $submission->novel()->firstOrFail()
                    : Novel::create([
                        'author_id' => $submission->user_id,
                        'title' => $submission->title,
                        'slug' => $this->uniqueSlug($submission->title),
                        'synopsis' => $submission->synopsis,
                        'cover_url' => $submission->cover_url,
                        'status' => 'published',
                        'published_at' => $reviewedAt,
                    ]);

                if ($submission->category_id) {
                    $novel->categories()->syncWithoutDetaching([$submission->category_id]);
                }

                if (! $novel->chapters()->exists()) {
                    $novel->chapters()->create([
                        'chapter_number' => 1,
                        'title' => __('ui.messages.untitled_chapter'),
                        'content' => $submission->manuscript,
                        'content_format' => $submission->manuscript_format ?? 'markdown',
                        'status' => 'published',
                        'published_at' => $reviewedAt,
                    ]);
                }
            }

            $submission->update([
                ...$data,
                'novel_id' => $novel?->id ?? $submission->novel_id,
                'reviewer_id' => $reviewerId,
                'reviewed_at' => $reviewedAt,
            ]);

            $this->audit($request, 'submission.'.$data['status'], $submission, [
                'submission_id' => $submission->id,
                'title' => $submission->title,
                'author_id' => $submission->user_id,
                'reviewer_id' => $reviewerId,
                'status' => $data['status'],
                'review_note' => $data['review_note'] ?? null,
                'novel_id' => $novel?->id,
            ]);

            return $submission->fresh(['novel', 'category', 'reviewer:id,name']);
        });

        if (! $this->wantsJson($request)) {
            return back()->with('status', $data['status'] === 'approved'
                ? __('ui.messages.submission_approved')
                : __('ui.messages.submission_rejected'));
        }

        return response()->json(['message' => __('ui.messages.operation_success'), 'submission' => $submission]);
    }

    public function auditLogs(Request $request)
    {
        $logs = AuditLog::query()
            ->where('action', 'like', 'submission.%')
            ->with('user:id,name')
            ->latest()
            ->paginate(config('yuejing.pagination'))
            ->withQueryString();

        $logs->getCollection()->loadMorph('auditable', [
            Submission::class => [
                'user:id,name,email',
                'category:id,name',
                'reviewer:id,name',
            ],
        ]);

        if (! $this->wantsJson($request)) {
            return view('pages.admin.audit-logs', compact('logs'));
        }

        return response()->json($logs);
    }

    private function validateNovel(Request $request, ?Novel $novel = null): array
    {
        return $request->validate([
            'author_id' => [$novel ? 'sometimes' : 'required', 'exists:users,id'],
            'title' => [$novel ? 'sometimes' : 'required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:novels,slug,'.($novel?->id ?? 'NULL')],
            'synopsis' => ['nullable', 'string'],
            'cover_url' => ['nullable', 'url', 'max:500'],
            'status' => ['sometimes', 'in:draft,published,archived'],
            'category_ids' => ['sometimes', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
        ]);
    }

    private function setNovelPublicationTimestamp(array $data, ?Novel $novel = null): array
    {
        if (($data['status'] ?? $novel?->status) === 'published') {
            $data['published_at'] ??= $novel?->published_at ?? now();
        } elseif (($data['status'] ?? null) === 'draft') {
            $data['published_at'] = null;
        }

        return $data;
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'novel';
        $slug = $base;
        $suffix = 1;
        while (Novel::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    private function audit(Request $request, string $action, object $model, array $metadata = []): void
    {
        AuditLog::create([
            'user_id' => $request->user()?->id,
            'action' => $action,
            'auditable_type' => $model::class,
            'auditable_id' => $model->getKey(),
            'metadata' => $metadata,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
