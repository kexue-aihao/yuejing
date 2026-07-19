<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Novel;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class AuthorNovelController extends Controller
{
    public function index(Request $request): JsonResponse|View
    {
        $query = Novel::query()
            ->with(['author:id,name', 'categories:id,name,slug'])
            ->withCount('chapters')
            ->latest();

        if (! $this->canManageAll($request)) {
            $query->where('author_id', $request->user()->id);
        }

        $novels = $query->paginate(config('yuejing.pagination'))->withQueryString();

        if (! $this->wantsJson($request)) {
            return view('pages.author.novels.index', compact('novels'));
        }

        return response()->json($novels);
    }

    public function edit(Request $request, Novel $novel): JsonResponse|View
    {
        $this->authorizeNovel($request, $novel);

        $novel->load(['categories:id,name,slug', 'author:id,name']);
        $selectedCategoryIds = $novel->categories->modelKeys();
        $categories = Category::query()
            ->where(function ($query) use ($selectedCategoryIds): void {
                $query->where('is_active', true)->orWhereIn('id', $selectedCategoryIds);
            })
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'is_active']);

        $payload = [
            'novel' => $novel,
            'categories' => $categories,
            'statusOptions' => ['draft', 'published', 'archived'],
        ];

        if (! $this->wantsJson($request)) {
            return view('pages.author.novels.edit', $payload);
        }

        return response()->json($payload);
    }

    public function update(Request $request, Novel $novel): JsonResponse|RedirectResponse
    {
        $this->authorizeNovel($request, $novel);

        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'synopsis' => ['nullable', 'string', 'max:5000'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'category_ids' => ['sometimes', 'array'],
            'category_ids.*' => ['integer', 'distinct', 'exists:categories,id'],
            'status' => ['sometimes', 'required', 'in:draft,published,archived'],
            'cover' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'cover_url' => ['nullable', 'url', 'max:500'],
        ]);

        $oldCoverUrl = $novel->cover_url;
        $newCoverPath = null;
        $coverReplaced = false;

        try {
            if (! array_key_exists('category_ids', $data) && array_key_exists('category_id', $data)) {
                $data['category_ids'] = $data['category_id'] === null ? [] : [$data['category_id']];
            }

            if ($request->hasFile('cover')) {
                $newCoverPath = $request->file('cover')->store('covers', 'public');
                $data['cover_url'] = Storage::disk('public')->url($newCoverPath);
                $coverReplaced = true;
            } elseif (array_key_exists('cover_url', $data)) {
                $coverReplaced = $data['cover_url'] !== $oldCoverUrl;
            }

            unset($data['cover']);
            unset($data['category_id']);
            $categoryIds = array_key_exists('category_ids', $data) ? $data['category_ids'] : null;
            unset($data['category_ids']);
            $data = $this->setPublicationTimestamp($data, $novel);

            $novel = DB::transaction(function () use ($data, $categoryIds, $novel, $request): Novel {
                $novel->update($data);
                if ($categoryIds !== null) {
                    $novel->categories()->sync($categoryIds);
                }

                AuditLog::create([
                    'user_id' => $request->user()->id,
                    'action' => 'novel.updated',
                    'auditable_type' => $novel::class,
                    'auditable_id' => $novel->id,
                    'metadata' => [
                        'novel_id' => $novel->id,
                        'author_id' => $novel->author_id,
                        'category_ids' => $categoryIds,
                        'status' => $novel->status,
                    ],
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);

                return $novel->fresh(['author:id,name', 'categories:id,name,slug']);
            });

            if ($coverReplaced && $oldCoverUrl !== $novel->cover_url) {
                $this->deleteLocalCover($oldCoverUrl);
            }
        } catch (Throwable $exception) {
            if ($newCoverPath !== null) {
                Storage::disk('public')->delete($newCoverPath);
            }

            throw $exception;
        }

        if (! $this->wantsJson($request)) {
            return redirect()->route('author.novels.edit', $novel)->with('status', __('ui.messages.novel_updated'));
        }

        return response()->json($novel);
    }

    public function chapters(Request $request, Novel $novel): JsonResponse|View
    {
        $this->authorizeNovel($request, $novel);

        $chapters = $novel->chapters()->paginate(config('yuejing.pagination'))->withQueryString();

        $payload = [
            'novel' => $novel->load(['author:id,name', 'categories:id,name,slug']),
            'chapters' => $chapters,
        ];

        if (! $this->wantsJson($request)) {
            return view('pages.author.novels.chapters', $payload);
        }

        return response()->json($payload);
    }

    private function canManageAll(Request $request): bool
    {
        return $request->user()->isRole(['editor', 'admin']);
    }

    private function authorizeNovel(Request $request, Novel $novel): void
    {
        abort_unless($this->canManageAll($request) || $novel->author_id === $request->user()->id, 403);
    }

    private function setPublicationTimestamp(array $data, Novel $novel): array
    {
        if (($data['status'] ?? $novel->status) === 'published') {
            $data['published_at'] ??= $novel->published_at ?? now();
        } elseif (($data['status'] ?? null) === 'draft') {
            $data['published_at'] = null;
        }

        return $data;
    }

    private function deleteLocalCover(?string $coverUrl): void
    {
        $path = parse_url((string) $coverUrl, PHP_URL_PATH) ?: (string) $coverUrl;
        $path = ltrim($path, '/');

        if (Str::startsWith($path, 'storage/covers/')) {
            Storage::disk('public')->delete(Str::after($path, 'storage/'));
        }
    }
}
