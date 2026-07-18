<?php

namespace App\Http\Controllers;

use App\Models\Favorite;
use App\Models\Novel;
use App\Models\Rating;
use App\Services\RatingScale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InteractionController extends Controller
{
    public function rate(Request $request, Novel $novel, RatingScale $scale)
    {
        abort_unless($novel->status === 'published', 404);
        $data = $request->validate([
            'rating' => ['required'],
            'review' => ['nullable', 'string', 'max:2000'],
            'criteria' => ['nullable', 'array'],
            'criteria.plot' => ['nullable', 'integer', 'min:1', 'max:10'],
            'criteria.writing' => ['nullable', 'integer', 'min:1', 'max:10'],
            'criteria.characters' => ['nullable', 'integer', 'min:1', 'max:10'],
            'criteria.originality' => ['nullable', 'integer', 'min:1', 'max:10'],
        ]);
        $data['rating'] = $scale->normalize($data['rating']);
        $rating = DB::transaction(function () use ($request, $novel, $data) {
            $rating = Rating::query()
                ->where('user_id', $request->user()->id)
                ->where('novel_id', $novel->id)
                ->lockForUpdate()
                ->first();

            if ($rating !== null && $rating->withdrawn_at === null) {
                abort(409, __('reviews.withdraw_before_rerating'));
            }

            $rating ??= new Rating(['user_id' => $request->user()->id, 'novel_id' => $novel->id]);
            $rating->fill([...$data, 'withdrawn_at' => null])->save();

            return $rating;
        });

        if (! $this->wantsJson($request)) {
            return back()->with('status', __('reviews.rating_saved'));
        }

        return response()->json(['message' => __('reviews.rating_saved'), 'rating' => $rating, 'level' => $scale->key($rating->rating), 'criteria' => $rating->criteria ?? []]);
    }

    public function withdrawRating(Request $request, Novel $novel)
    {
        Rating::query()
            ->where('user_id', $request->user()->id)
            ->where('novel_id', $novel->id)
            ->whereNull('withdrawn_at')
            ->update(['withdrawn_at' => now()]);

        if (! $this->wantsJson($request)) {
            return back()->with('status', __('reviews.rating_withdrawn'));
        }

        return response()->json(['message' => __('reviews.rating_withdrawn')]);
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
