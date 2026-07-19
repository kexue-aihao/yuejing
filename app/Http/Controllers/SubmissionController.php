<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Submission;
use App\Services\ManuscriptFileParser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class SubmissionController extends Controller
{
    public function index(Request $request)
    {
        if (! $this->wantsJson($request)) {
            if ($request->routeIs('author.submissions')) {
                return redirect()->route('dashboard', ['section' => 'submissions']);
            }

            $submissions = $request->user()->submissions()->with('reviewer:id,name')->latest()->paginate(config('yuejing.pagination'));
            $categories = Category::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'slug']);

            return view('pages.author.submissions', [
                'submissions' => $submissions,
                'categories' => $categories,
                'requiresSynopsis' => $request->user()->submissions()->doesntExist(),
            ]);
        }

        $submissions = $request->user()->submissions()->with('reviewer:id,name')->latest()->paginate(config('yuejing.pagination'));

        return response()->json($submissions);
    }

    public function store(Request $request, ManuscriptFileParser $fileParser)
    {
        $requiresSynopsis = $request->user()->submissions()->doesntExist();
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'synopsis' => ['nullable', 'string', 'max:5000'],
            'manuscript' => ['nullable', 'string'],
            'manuscript_format' => ['nullable', 'in:markdown,text'],
            'manuscript_file' => ['nullable', 'file', 'max:5120'],
            'summary' => ['nullable', 'string', 'max:5000'],
            'content' => ['nullable', 'string'],
            'cover' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'cover_url' => ['nullable', 'url', 'max:500'],
        ]);

        $data['synopsis'] = filled($data['synopsis'] ?? null)
            ? $data['synopsis']
            : ($data['summary'] ?? null);
        $editorContent = $data['manuscript'] ?? $data['content'] ?? null;
        $hasEditorContent = is_string($editorContent) && trim($editorContent) !== '';
        $hasUploadedFile = $request->hasFile('manuscript_file');

        if ($hasEditorContent && $hasUploadedFile) {
            throw ValidationException::withMessages([
                'manuscript_file' => [__('ui.messages.manuscript_source_conflict')],
            ]);
        }

        if ($hasUploadedFile) {
            $parsed = $fileParser->parse($request->file('manuscript_file'));
            $data['manuscript'] = $parsed['content'];
            $data['manuscript_format'] = $parsed['format'];
        } else {
            $data['manuscript'] = $editorContent;
            $data['manuscript_format'] = 'markdown';
        }

        unset($data['summary'], $data['content'], $data['manuscript_file']);

        if (! is_string($data['manuscript']) || trim($data['manuscript']) === '') {
            if ($this->wantsJson($request)) {
                return response()->json([
                    'message' => trans('validation.required', ['attribute' => trans('validation.attributes.manuscript')]),
                    'errors' => ['manuscript' => [trans('validation.required', ['attribute' => trans('validation.attributes.manuscript')])]],
                ], 422);
            }

            return back()->withErrors(['content' => __('ui.messages.submission_content_required')])->withInput();
        }

        if ($requiresSynopsis && blank($data['synopsis'])) {
            throw ValidationException::withMessages([
                'summary' => [__('ui.messages.submission_synopsis_required')],
            ]);
        }

        if (! $request->hasFile('cover') && blank($data['cover_url'] ?? null)) {
            throw ValidationException::withMessages([
                'cover' => [trans('validation.required', ['attribute' => trans('ui.author.cover_label')])],
            ]);
        }

        $coverPath = null;
        try {
            $data['cover_url'] = $data['cover_url'] ?? null;
            if ($request->hasFile('cover')) {
                $coverPath = $request->file('cover')->store('covers', 'public');
                $data['cover_url'] = Storage::disk('public')->url($coverPath);
            }
            unset($data['cover']);

            $submission = DB::transaction(function () use ($data, $request): Submission {
                $submission = $request->user()->submissions()->create($data);

                AuditLog::create([
                    'user_id' => $request->user()->id,
                    'action' => 'submission.created',
                    'auditable_type' => $submission::class,
                    'auditable_id' => $submission->id,
                    'metadata' => [
                        'submission_id' => $submission->id,
                        'title' => $submission->title,
                        'author_id' => $submission->user_id,
                        'category_id' => $submission->category_id,
                        'status' => $submission->status,
                    ],
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);

                return $submission;
            });
        } catch (Throwable $exception) {
            if ($coverPath !== null) {
                Storage::disk('public')->delete($coverPath);
            }

            throw $exception;
        }

        if (! $this->wantsJson($request)) {
            return redirect()->route('dashboard', ['section' => 'submissions'])->with('status', __('ui.messages.submission_created'));
        }

        return response()->json(['message' => __('ui.messages.submission_created'), 'submission' => $submission], 201);
    }

    public function show(Request $request, Submission $submission)
    {
        abort_unless($submission->user_id === $request->user()->id || $request->user()->isRole(['editor', 'admin']), 403);

        return response()->json($submission->load('reviewer:id,name'));
    }
}
