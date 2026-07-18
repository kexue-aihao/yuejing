<?php

namespace App\Http\Controllers;

use App\Services\RecommendationService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RecommendationController extends Controller
{
    public function index(Request $request, RecommendationService $recommendations)
    {
        return $this->payload($request, $recommendations, false);
    }

    public function stream(Request $request, RecommendationService $recommendations): StreamedResponse
    {
        $limit = max(1, min($request->integer('limit', 6), 12));
        $payload = $this->recommendationPayload($request, $recommendations, $limit);

        return response()->stream(function () use ($payload): void {
            echo 'retry: 60000'."\n\n";
            echo 'event: recommendations'."\n";
            echo 'data: '.json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n\n";
            if (function_exists('ob_flush')) {
                @ob_flush();
            }
            flush();
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    private function payload(Request $request, RecommendationService $recommendations, bool $stream)
    {
        $limit = max(1, min($request->integer('limit', 6), 12));
        return response()->json($this->recommendationPayload($request, $recommendations, $limit, $stream));
    }

    private function recommendationPayload(Request $request, RecommendationService $recommendations, int $limit, bool $stream = true): array
    {
        $items = $recommendations->for($request->user(), $request, $limit);

        return [
            'data' => $items->map(fn ($novel) => [
                'id' => $novel->id,
                'title' => $novel->title,
                'slug' => $novel->slug,
                'author' => $novel->author?->name,
                'categories' => $novel->categories->pluck('name')->values(),
                'cover_url' => $novel->cover_url,
                'views_count' => $novel->views_count,
            ])->values(),
            'generated_at' => now()->toIso8601String(),
            'next_poll_after' => $stream ? 60 : null,
        ];
    }
}
