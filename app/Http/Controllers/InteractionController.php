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
            return back()->with('status', __('ui.messages.rating_saved'));
        }

        return response()->json(['message' => __('ui.messages.rating_saved'), 'rating' => $rating]);
    }

    public function favorite(Request $request, Novel $novel)
    {
        abort_unless($novel->status === 'published', 404);
        $favorite = Favorite::firstOrCreate(['user_id' => $request->user()->id, 'novel_id' => $novel->id]);

        if (! $this->wantsJson($request)) {
            return back()->with('status', __('ui.messages.favorite_added'));
        }

        return response()->json(['message' => __('ui.messages.favorite_added'), 'favorite' => $favorite], 201);
    }

    public function unfavorite(Request $request, Novel $novel)
    {
        Favorite::where('user_id', $request->user()->id)->where('novel_id', $novel->id)->delete();

        if (! $this->wantsJson($request)) {
            return back()->with('status', __('ui.messages.favorite_removed'));
        }

        return response()->json(['message' => __('ui.messages.favorite_removed')]);
    }

    public function readings(Request $request)
    {
        $records = $request->user()->readingRecords()
            ->with(['novel:id,title,slug,author_id', 'novel.author:id,name', 'chapter:id,title,chapter_number'])
            ->latest('last_read_at')
            ->paginate(config('yuejing.pagination'));

        if ($this->wantsJson($request)) {
            return response()->json($records);
        }

        return view('pages.account.reading-records', compact('records'));
    }

    public function favorites(Request $request)
    {
        $favorites = $request->user()->favorites()
            ->with('novel.author:id,name')
            ->whereHas('novel', fn ($query) => $query->where('status', 'published'))
            ->latest()
            ->paginate(config('yuejing.pagination'));

        if ($this->wantsJson($request)) {
            return response()->json($favorites);
        }

        return view('pages.account.favorites', compact('favorites'));
    }
}
