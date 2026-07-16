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
use Illuminate\Http\Request;

class AdminPageController extends Controller
{
    public function dashboard()
    {
        return view('pages.admin.dashboard', [
            'users' => User::count(),
            'novels' => Novel::count(),
            'chapters' => Chapter::count(),
            'pending_submissions' => Submission::where('status', 'pending')->count(),
            'recent_submissions' => Submission::with('user:id,name')->latest()->limit(5)->get(),
            'recent_novels' => Novel::with('author:id,name')->latest()->limit(5)->get(),
        ]);
    }

    public function settings(AppSettingService $service)
    {
        return view('pages.admin.settings', [
            'settingValues' => [
                'site_name' => $service->get('site_name', config('app.name', '阅境')),
                'site_tagline' => $service->get('site_tagline', '在故事里相遇'),
                'contact_email' => $service->get('contact_email', config('mail.from.address', '')),
                'accent_color' => $service->get('accent_color', 'coral'),
                'email_verification_required' => (bool) $service->get('email_verification_required', false),
                'show_rank' => (bool) $service->get('show_rank', true),
                'show_new' => (bool) $service->get('show_new', true),
                'allow_comments' => (bool) $service->get('allow_comments', false),
            ],
        ]);
    }

    public function categories()
    {
        return view('pages.admin.categories', [
            'categories' => Category::withCount('novels')->latest()->paginate(config('yuejing.pagination')),
        ]);
    }

    public function novels()
    {
        return view('pages.admin.novels', [
            'novels' => Novel::with('author:id,name')->withCount('chapters')->latest()->paginate(config('yuejing.pagination')),
        ]);
    }

    public function submissions(Request $request)
    {
        $query = Submission::with(['user:id,name,email', 'novel:id,title', 'category:id,name'])->latest();
        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        return view('pages.admin.submissions', ['submissions' => $query->paginate(config('yuejing.pagination'))]);
    }

    public function chapters(Novel $novel)
    {
        return view('pages.admin.chapters', [
            'novel' => $novel->load('author:id,name'),
            'chapters' => $novel->chapters()->paginate(config('yuejing.pagination')),
        ]);
    }

    public function auditLogs()
    {
        return view('pages.admin.audit-logs', [
            'logs' => AuditLog::with('user:id,name')->latest()->paginate(config('yuejing.pagination')),
        ]);
    }
}
