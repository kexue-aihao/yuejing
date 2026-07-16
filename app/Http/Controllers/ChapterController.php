<?php

namespace App\Http\Controllers;

use App\Models\Chapter;
use App\Models\Novel;
use Illuminate\Http\Request;

class ChapterController extends Controller
{
    public function index(Request $request, Novel $novel)
    {
        $this->authorizeNovel($request, $novel);

        $chapters = $novel->chapters()->paginate(config('yuejing.pagination'));

        if (! $this->wantsJson($request)) {
            return view('pages.admin.chapters', compact('novel', 'chapters'));
        }

        return response()->json($chapters);
    }

    public function store(Request $request, Novel $novel)
    {
        $this->authorizeNovel($request, $novel);
        $data = $request->validate(['chapter_number' => ['required', 'integer', 'min:1'], 'title' => ['required', 'string', 'max:255'], 'content' => ['required', 'string'], 'status' => ['sometimes', 'in:draft,published']]);
        $data = $this->setPublicationTimestamp($data);
        $chapter = $novel->chapters()->create($data);

        if (! $this->wantsJson($request)) {
            return back()->with('status', '章节已创建。');
        }

        return response()->json($chapter, 201);
    }

    public function update(Request $request, Novel $novel, Chapter $chapter)
    {
        $this->authorizeNovel($request, $novel);
        abort_unless($chapter->novel_id === $novel->id, 404);
        $data = $request->validate(['chapter_number' => ['sometimes', 'integer', 'min:1'], 'title' => ['sometimes', 'string', 'max:255'], 'content' => ['sometimes', 'string'], 'status' => ['sometimes', 'in:draft,published']]);
        $chapter->update($this->setPublicationTimestamp($data, $chapter));

        if (! $this->wantsJson($request)) {
            return back()->with('status', '章节已更新。');
        }

        return response()->json($chapter);
    }

    public function destroy(Request $request, Novel $novel, Chapter $chapter)
    {
        $this->authorizeNovel($request, $novel);
        abort_unless($chapter->novel_id === $novel->id, 404);
        $chapter->delete();

        if (! $this->wantsJson($request)) {
            return back()->with('status', '章节已删除。');
        }

        return response()->json(['message' => 'Chapter deleted.']);
    }

    private function setPublicationTimestamp(array $data, ?Chapter $chapter = null): array
    {
        if (($data['status'] ?? $chapter?->status) === 'published') {
            $data['published_at'] ??= $chapter?->published_at ?? now();
        } elseif (($data['status'] ?? null) === 'draft') {
            $data['published_at'] = null;
        }

        return $data;
    }

    private function authorizeNovel(Request $request, Novel $novel): void
    {
        abort_unless(
            $request->user()->isRole(['editor', 'admin']) || $novel->author_id === $request->user()->id,
            403,
        );
    }
}
