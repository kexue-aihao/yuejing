<?php

namespace App\Http\Controllers;

use App\Models\Chapter;
use App\Models\Novel;
use App\Services\ManuscriptFileParser;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

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

    public function store(Request $request, Novel $novel, ManuscriptFileParser $fileParser)
    {
        $this->authorizeNovel($request, $novel);
        $data = $request->validate([
            'chapter_number' => ['required', 'integer', 'min:1'],
            'title' => ['required', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
            'content_format' => ['nullable', 'in:markdown,text'],
            'chapter_file' => ['nullable', 'file', 'max:5120'],
            'status' => ['sometimes', 'in:draft,published'],
        ]);
        $data = $this->resolveContent($request, $data, $fileParser, true);
        $data = $this->setPublicationTimestamp($data);
        $chapter = $novel->chapters()->create($data);

        if (! $this->wantsJson($request)) {
            return back()->with('status', __('ui.messages.chapter_created'));
        }

        return response()->json($chapter, 201);
    }

    public function update(Request $request, Novel $novel, Chapter $chapter, ManuscriptFileParser $fileParser)
    {
        $this->authorizeNovel($request, $novel);
        abort_unless($chapter->novel_id === $novel->id, 404);
        $data = $request->validate([
            'chapter_number' => ['sometimes', 'integer', 'min:1'],
            'title' => ['sometimes', 'string', 'max:255'],
            'content' => ['sometimes', 'nullable', 'string'],
            'content_format' => ['sometimes', 'in:markdown,text'],
            'chapter_file' => ['nullable', 'file', 'max:5120'],
            'status' => ['sometimes', 'in:draft,published'],
        ]);
        $data = $this->resolveContent($request, $data, $fileParser, false, $chapter->content_format ?? 'markdown');
        $chapter->update($this->setPublicationTimestamp($data, $chapter));

        if (! $this->wantsJson($request)) {
            return back()->with('status', __('ui.messages.chapter_updated'));
        }

        return response()->json($chapter);
    }

    public function destroy(Request $request, Novel $novel, Chapter $chapter)
    {
        $this->authorizeNovel($request, $novel);
        abort_unless($chapter->novel_id === $novel->id, 404);
        $chapter->delete();

        if (! $this->wantsJson($request)) {
            return back()->with('status', __('ui.messages.chapter_deleted'));
        }

        return response()->json(['message' => __('ui.messages.chapter_deleted')]);
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

    private function resolveContent(Request $request, array $data, ManuscriptFileParser $fileParser, bool $required = false, string $existingFormat = 'markdown'): array
    {
        $editorContent = $data['content'] ?? null;
        $hasEditorContent = is_string($editorContent) && trim($editorContent) !== '';
        $hasUploadedFile = $request->hasFile('chapter_file');

        if ($hasEditorContent && $hasUploadedFile) {
            throw ValidationException::withMessages([
                'chapter_file' => [__('ui.messages.manuscript_source_conflict')],
            ]);
        }

        if ($hasUploadedFile) {
            $parsed = $fileParser->parse($request->file('chapter_file'));
            $data['content'] = $parsed['content'];
            $data['content_format'] = $parsed['format'];
        } elseif ($required && ! $hasEditorContent) {
            throw ValidationException::withMessages([
                'content' => [__('ui.messages.chapter_content_required')],
            ]);
        } elseif (array_key_exists('content', $data) && ! $hasEditorContent) {
            throw ValidationException::withMessages([
                'content' => [__('ui.messages.chapter_content_required')],
            ]);
        } elseif (array_key_exists('content', $data)) {
            $data['content_format'] = $data['content_format'] ?? $existingFormat;
        }

        unset($data['chapter_file']);

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
