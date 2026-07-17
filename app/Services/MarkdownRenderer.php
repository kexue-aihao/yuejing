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

    public function render(?string $markdown): string
    {
        if ($markdown === null || trim($markdown) === '') {
            return '';
        }

        return (string) $this->converter->convert($markdown);
    }
}
