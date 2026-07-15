<?php

namespace App\Http\Controllers;

use App\Models\Submission;
use Illuminate\Http\Request;

class SubmissionController extends Controller
{
    public function index(Request $request)
    {
        $submissions = $request->user()->submissions()->with('reviewer:id,name')->latest()->paginate(config('yuejing.pagination'));

        if (! $this->wantsJson($request)) {
            return view('pages.author.submissions', compact('submissions'));
        }

        return response()->json($submissions);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'synopsis' => ['nullable', 'string', 'max:5000'],
            'manuscript' => ['nullable', 'string'],
            'summary' => ['nullable', 'string', 'max:5000'],
            'content' => ['nullable', 'string'],
        ]);

        $data['synopsis'] = $data['synopsis'] ?? $data['summary'] ?? null;
        $data['manuscript'] = $data['manuscript'] ?? $data['content'] ?? null;
        unset($data['summary'], $data['content']);

        if ($data['manuscript'] === null) {
            if ($this->wantsJson($request)) {
                return response()->json([
                    'message' => 'The manuscript field is required.',
                    'errors' => ['manuscript' => ['The manuscript field is required.']],
                ], 422);
            }

            return back()->withErrors(['content' => '请填写首章内容。'])->withInput();
        }

        $submission = $request->user()->submissions()->create($data);

        if (! $this->wantsJson($request)) {
            return redirect()->route('author.submissions')->with('status', '投稿已提交，等待审核。');
        }

        return response()->json(['message' => 'Submission created.', 'submission' => $submission], 201);
    }

    public function show(Request $request, Submission $submission)
    {
        abort_unless($submission->user_id === $request->user()->id || $request->user()->isRole(['editor', 'admin']), 403);

        return response()->json($submission->load('reviewer:id,name'));
    }
}
