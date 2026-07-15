<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Chapter;
use App\Models\Novel;
use App\Models\Submission;
use App\Models\User;
use App\Services\AppSettingService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    public function dashboard()
    {
        return response()->json([
            'users' => User::count(), 'novels' => Novel::count(), 'chapters' => Chapter::count(),
            'pending_submissions' => Submission::where('status', 'pending')->count(),
        ]);
    }

    public function settings(Request $request)
    {
        return response()->json(['settings' => \App\Models\Setting::query()->get()]);
    }

    public function updateSettings(Request $request, AppSettingService $service)
    {
        $data = $request->validate(['email_verification_required' => ['sometimes', 'boolean'], 'site_name' => ['sometimes', 'string', 'max:255']]);
        foreach ($data as $key => $value) {
            $service->set($key, $value, $request->user()->id);
        }

        return response()->json(['message' => 'Settings updated.']);
    }

    public function categories()
    {
        return response()->json(Category::latest()->paginate(config('yuejing.pagination')));
    }

    public function storeCategory(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'slug' => ['nullable', 'string', 'max:100', 'unique:categories,slug'], 'description' => ['nullable', 'string'], 'is_active' => ['sometimes', 'boolean']]);
        $data['slug'] ??= Str::slug($data['name']);
        $category = Category::create($data);

        return response()->json($category, 201);
    }

    public function updateCategory(Request $request, Category $category)
    {
        $data = $request->validate(['name' => ['sometimes', 'string', 'max:100'], 'slug' => ['sometimes', 'string', 'max:100', 'unique:categories,slug,'.$category->id], 'description' => ['nullable', 'string'], 'is_active' => ['sometimes', 'boolean']]);
        $category->update($data);

        return response()->json($category);
    }

    public function destroyCategory(Category $category)
    {
        $category->delete();
        return response()->json(['message' => 'Category deleted.']);
    }

    public function novels()
    {
        return response()->json(Novel::with('author:id,name')->latest()->paginate(config('yuejing.pagination')));
    }

    public function storeNovel(Request $request)
    {
        $data = $request->validate(['author_id' => ['required', 'exists:users,id'], 'title' => ['required', 'string', 'max:255'], 'slug' => ['nullable', 'string', 'max:255', 'unique:novels,slug'], 'synopsis' => ['nullable', 'string'], 'cover_url' => ['nullable', 'url', 'max:500'], 'status' => ['sometimes', 'in:draft,published,archived'], 'category_ids' => ['sometimes', 'array'], 'category_ids.*' => ['integer', 'exists:categories,id']]);
        $categoryIds = $data['category_ids'] ?? [];
        unset($data['category_ids']);
        $data['slug'] ??= Str::slug($data['title']).'-'.Str::lower(Str::random(5));
        $novel = Novel::create($data);
        $novel->categories()->sync($categoryIds);

        return response()->json($novel->load('categories'), 201);
    }

    public function updateNovel(Request $request, Novel $novel)
    {
        $data = $request->validate(['title' => ['sometimes', 'string', 'max:255'], 'slug' => ['sometimes', 'string', 'max:255', 'unique:novels,slug,'.$novel->id], 'synopsis' => ['nullable', 'string'], 'cover_url' => ['nullable', 'url', 'max:500'], 'status' => ['sometimes', 'in:draft,published,archived'], 'category_ids' => ['sometimes', 'array'], 'category_ids.*' => ['integer', 'exists:categories,id']]);
        $categoryIds = $data['category_ids'] ?? null;
        unset($data['category_ids']);
        $novel->update($data);
        if ($categoryIds !== null) { $novel->categories()->sync($categoryIds); }

        return response()->json($novel->load('categories'));
    }

    public function destroyNovel(Novel $novel)
    {
        $novel->delete();
        return response()->json(['message' => 'Novel deleted.']);
    }

    public function submissions(Request $request)
    {
        $query = Submission::with(['user:id,name,email', 'novel:id,title'])->latest();
        if ($request->filled('status')) { $query->where('status', $request->string('status')); }
        return response()->json($query->paginate(config('yuejing.pagination')));
    }

    public function reviewSubmission(Request $request, Submission $submission)
    {
        $data = $request->validate(['status' => ['required', 'in:approved,rejected'], 'review_note' => ['nullable', 'string', 'max:5000']]);
        $submission->update([...$data, 'reviewer_id' => $request->user()->id, 'reviewed_at' => now()]);

        return response()->json(['message' => 'Submission reviewed.', 'submission' => $submission]);
    }

    public function auditLogs()
    {
        return response()->json(AuditLog::with('user:id,name')->latest()->paginate(config('yuejing.pagination')));
    }
}
