<?php

namespace App\Http\Controllers;

use App\Models\Chapter;
use App\Models\Novel;
use Illuminate\Http\Request;

class ChapterController extends Controller
{
    public function store(Request $request, Novel $novel)
    {
        $this->authorizeNovel($request, $novel);
        $data = $request->validate(['chapter_number' => ['required', 'integer', 'min:1'], 'title' => ['required', 'string', 'max:255'], 'content' => ['required', 'string'], 'status' => ['sometimes', 'in:draft,published']]);
        $chapter = $novel->chapters()->create($data);

        return response()->json($chapter, 201);
    }

    public function update(Request $request, Novel $novel, Chapter $chapter)
    {
        $this->authorizeNovel($request, $novel);
        abort_unless($chapter->novel_id === $novel->id, 404);
        $chapter->update($request->validate(['chapter_number' => ['sometimes', 'integer', 'min:1'], 'title' => ['sometimes', 'string', 'max:255'], 'content' => ['sometimes', 'string'], 'status' => ['sometimes', 'in:draft,published']]));

        return response()->json($chapter);
    }

    public function destroy(Request $request, Novel $novel, Chapter $chapter)
    {
        $this->authorizeNovel($request, $novel);
        abort_unless($chapter->novel_id === $novel->id, 404);
        $chapter->delete();
        return response()->json(['message' => 'Chapter deleted.']);
    }

    private function authorizeNovel(Request $request, Novel $novel): void
    {
        abort_unless(
            $request->user()->isRole(['editor', 'admin']) || $novel->author_id === $request->user()->id,
            403,
        );
    }
}
