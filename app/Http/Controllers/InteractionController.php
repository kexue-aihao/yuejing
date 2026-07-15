<?php

namespace App\Http\Controllers;

use App\Models\Favorite;
use App\Models\Novel;
use App\Models\Rating;
use Illuminate\Http\Request;

class InteractionController extends Controller
{
    public function rate(Request $request, Novel $novel)
    {
        abort_unless($novel->status === 'published', 404);
        $data = $request->validate(['rating' => ['required', 'integer', 'min:1', 'max:5'], 'review' => ['nullable', 'string', 'max:2000']]);
        $rating = Rating::updateOrCreate(['user_id' => $request->user()->id, 'novel_id' => $novel->id], $data);

        if (! $this->wantsJson($request)) {
            return back()->with('status', '评分已保存。');
        }

        return response()->json(['message' => 'Rating saved.', 'rating' => $rating]);
    }

    public function favorite(Request $request, Novel $novel)
    {
        abort_unless($novel->status === 'published', 404);
        $favorite = Favorite::firstOrCreate(['user_id' => $request->user()->id, 'novel_id' => $novel->id]);

        if (! $this->wantsJson($request)) {
            return back()->with('status', '已加入收藏。');
        }

        return response()->json(['message' => 'Novel favorited.', 'favorite' => $favorite], 201);
    }

    public function unfavorite(Request $request, Novel $novel)
    {
        Favorite::where('user_id', $request->user()->id)->where('novel_id', $novel->id)->delete();

        if (! $this->wantsJson($request)) {
            return back()->with('status', '已取消收藏。');
        }

        return response()->json(['message' => 'Novel removed from favorites.']);
    }

    public function readings(Request $request)
    {
        $records = $request->user()->readingRecords()->with(['novel:id,title,slug', 'chapter:id,title,chapter_number'])->latest('last_read_at')->paginate(config('yuejing.pagination'));

        return $this->wantsJson($request)
            ? response()->json($records)
            : view('pages.dashboard', ['reading' => $records->getCollection()->map(fn ($record) => [
                'title' => $record->novel?->title ?? '未命名作品',
                'author' => '匿名作者',
                'progress' => '第 '.($record->chapter?->chapter_number ?? 1).' 章',
                'status' => '最近阅读',
                'slug' => $record->novel?->slug,
            ])]);
    }
}
