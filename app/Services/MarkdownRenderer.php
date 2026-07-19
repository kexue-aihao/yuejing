<?php

namespace App\Services;

use League\CommonMark\GithubFlavoredMarkdownConverter;

class MarkdownRenderer
{
    private GithubFlavoredMarkdownConverter $converter;

    public function __construct()
    {
        $this->converter = new GithubFlavoredMarkdownConverter([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }

    public function render(?string $markdown, string $format = 'markdown'): string
    {
        if ($markdown === null || trim($markdown) === '') {
            return '';
        }

        if ($format === 'text') {
            $text = str_replace(["\r\n", "\r"], "\n", $markdown);
            $paragraphs = preg_split('/\n{2,}/', trim($text)) ?: [];

            return collect($paragraphs)
                ->filter(static fn (string $paragraph): bool => trim($paragraph) !== '')
                ->map(static fn (string $paragraph): string => '<p>'.nl2br(htmlspecialchars(trim($paragraph), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), false).'</p>')
                ->implode('');
        }

        return (string) $this->converter->convert($markdown);
    }
}
