<?php

namespace App\Services;

use App\Models\Novel;
use App\Models\SearchEvent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

final class RecommendationService
{
    public function for(?User $user = null, ?Request $request = null, int $limit = 6): Collection
    {
        if (! Schema::hasTable('novels')) {
            return collect();
        }

        $excludedIds = collect();
        if ($user) {
            $excludedIds = $excludedIds
                ->merge($user->readingRecords()->pluck('novel_id'))
                ->merge($user->favorites()->pluck('novel_id'))
                ->merge($user->ratings()->whereNull('withdrawn_at')->pluck('novel_id'));
        }

        $categoryIds = $this->preferredCategoryIds($user, $request);
        $query = Novel::query()
            ->with(['author:id,name', 'categories:id,name'])
            ->where('status', 'published')
            ->when($excludedIds->isNotEmpty(), fn ($builder) => $builder->whereNotIn('id', $excludedIds->unique()->values()))
            ->when($categoryIds->isNotEmpty(), fn ($builder) => $builder->whereHas('categories', fn ($categories) => $categories->whereIn('categories.id', $categoryIds)))
            ->orderByDesc('views_count')
            ->latest('published_at')
            ->limit($limit);

        $recommendations = $query->get();

        if ($recommendations->count() < $limit) {
            $fallback = Novel::query()
                ->with(['author:id,name', 'categories:id,name'])
                ->where('status', 'published')
                ->whereNotIn('id', $excludedIds->merge($recommendations->pluck('id'))->unique()->values())
                ->orderByDesc('views_count')
                ->latest('published_at')
                ->limit($limit - $recommendations->count())
                ->get();

            $recommendations = $recommendations->concat($fallback);
        }

        return $recommendations->values();
    }

    private function preferredCategoryIds(?User $user, ?Request $request): Collection
    {
        if (! Schema::hasTable('search_events')) {
            return collect();
        }

        $events = SearchEvent::query()
            ->whereNotNull('category_id')
            ->when($user || $request, function ($query) use ($user, $request): void {
                $query->where(function ($scope) use ($user, $request): void {
                    if ($user) {
                        $scope->where('user_id', $user->id);
                    }
                    if ($request) {
                        $method = $user ? 'orWhere' : 'where';
                        $scope->{$method}('session_hash', $this->sessionHash($request));
                    }
                });
            })
            ->selectRaw('category_id, count(*) as total, max(created_at) as last_seen')
            ->groupBy('category_id')
            ->orderByDesc('total')
            ->orderByDesc('last_seen')
            ->limit(5)
            ->pluck('category_id');

        if ($user && Schema::hasTable('reading_records')) {
            $readCategoryIds = $user->readingRecords()
                ->with('novel.categories:id')
                ->latest('last_read_at')
                ->limit(8)
                ->get()
                ->flatMap(fn ($record) => $record->novel?->categories?->pluck('id') ?? [])
                ->unique()
                ->values();

            $events = $events->concat($readCategoryIds);
        }

        return $events->unique()->take(5)->values();
    }

    private function sessionHash(Request $request): string
    {
        return hash('sha256', (string) $request->session()->getId());
    }
}
