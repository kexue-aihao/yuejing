<?php

namespace Tests\Feature;

use App\Models\Chapter;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ManuscriptUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_markdown_upload_is_stored_without_persisting_the_original_file(): void
    {
        Storage::fake('public');
        $author = User::factory()->create(['role' => 'author']);

        $response = $this->actingAs($author)->postWithCsrf(route('author.submissions.store'), [
            'title' => 'Markdown upload',
            'summary' => 'A submitted story',
            'cover' => UploadedFile::fake()->image('cover.jpg'),
            'manuscript_file' => UploadedFile::fake()->createWithContent('chapter.md', "# Chapter one\n\nMarkdown body"),
        ]);

        $response->assertRedirect(route('dashboard', ['section' => 'submissions']));
        $submission = Submission::query()->where('title', 'Markdown upload')->firstOrFail();

        $this->assertSame('markdown', $submission->manuscript_format);
        $this->assertSame("# Chapter one\n\nMarkdown body", $submission->manuscript);
        Storage::disk('public')->assertMissing('chapter.md');
    }

    public function test_utf16_txt_upload_is_converted_and_saved_as_text(): void
    {
        Storage::fake('public');
        $author = User::factory()->create(['role' => 'author']);
        $utf16 = "\xFF\xFE".mb_convert_encoding("First paragraph\n\nSecond paragraph", 'UTF-16LE', 'UTF-8');

        $this->actingAs($author)->postWithCsrf(route('author.submissions.store'), [
            'title' => 'Text upload',
            'summary' => 'A text story',
            'cover' => UploadedFile::fake()->image('cover.jpg'),
            'manuscript_file' => UploadedFile::fake()->createWithContent('chapter.txt', $utf16),
        ])->assertRedirect(route('dashboard', ['section' => 'submissions']));

        $submission = Submission::query()->where('title', 'Text upload')->firstOrFail();
        $this->assertSame('text', $submission->manuscript_format);
        $this->assertSame("First paragraph\n\nSecond paragraph", $submission->manuscript);
    }

    public function test_editor_content_and_file_upload_cannot_be_submitted_together(): void
    {
        $author = User::factory()->create(['role' => 'author']);

        $this->actingAs($author)->postWithCsrf(route('author.submissions.store'), [
            'title' => 'Conflicting sources',
            'summary' => 'A story',
            'content' => 'Editor content',
            'cover' => UploadedFile::fake()->image('cover.jpg'),
            'manuscript_file' => UploadedFile::fake()->createWithContent('chapter.txt', 'File content'),
        ])->assertSessionHasErrors('manuscript_file');

        $this->assertDatabaseMissing('submissions', ['title' => 'Conflicting sources']);
    }

    public function test_approved_text_submission_keeps_text_format_on_first_chapter(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $author = User::factory()->create(['role' => 'author']);
        $submission = Submission::create([
            'user_id' => $author->id,
            'title' => 'Text approval',
            'manuscript' => 'Plain text body',
            'manuscript_format' => 'text',
            'cover_url' => 'https://example.test/cover.jpg',
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->putJsonWithCsrf(route('admin.submissions.review', $submission), ['status' => 'approved'])
            ->assertOk();

        $chapter = Chapter::query()->where('novel_id', $submission->fresh()->novel_id)->firstOrFail();
        $this->assertSame('text', $chapter->content_format);
    }
}
