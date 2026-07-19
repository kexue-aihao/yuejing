<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class ManuscriptFileParser
{
    private const MAX_BYTES = 5242880;

    /**
     * @return array{content: string, format: string}
     */
    public function parse(UploadedFile $file): array
    {
        $extension = strtolower((string) $file->getClientOriginalExtension());
        if (! in_array($extension, ['md', 'markdown', 'txt'], true)) {
            $this->fail(__('ui.messages.manuscript_file_type_invalid'));
        }

        $mime = strtolower((string) $file->getMimeType());
        $allowedMimes = [
            '',
            'application/octet-stream',
            'application/markdown',
            'application/x-markdown',
            'text/markdown',
            'text/plain',
            'text/x-markdown',
        ];
        if (! in_array($mime, $allowedMimes, true)) {
            $this->fail(__('ui.messages.manuscript_file_type_invalid'));
        }

        $size = $file->getSize();
        if ($size === false || $size > self::MAX_BYTES) {
            $this->fail(__('ui.messages.manuscript_file_too_large'));
        }

        $path = $file->getRealPath();
        $raw = is_string($path) ? file_get_contents($path) : false;
        if (! is_string($raw)) {
            $this->fail(__('ui.messages.manuscript_file_unreadable'));
        }

        $content = $this->decode($raw);
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;

        if (trim($content) === '') {
            $this->fail(__('ui.messages.manuscript_file_empty'));
        }

        return [
            'content' => $content,
            'format' => $extension === 'txt' ? 'text' : 'markdown',
        ];
    }

    private function decode(string $raw): string
    {
        if (str_starts_with($raw, "\xFF\xFE")) {
            return $this->convert($raw, 'UTF-16LE');
        }

        if (str_starts_with($raw, "\xFE\xFF")) {
            return $this->convert($raw, 'UTF-16BE');
        }

        if (function_exists('mb_check_encoding') && mb_check_encoding($raw, 'UTF-8')) {
            return $raw;
        }

        foreach (['GB18030', 'BIG5', 'Windows-1252'] as $encoding) {
            if (function_exists('mb_check_encoding') && mb_check_encoding($raw, $encoding)) {
                return $this->convert($raw, $encoding);
            }
        }

        $this->fail(__('ui.messages.manuscript_file_encoding_invalid'));
    }

    private function convert(string $value, string $from): string
    {
        if (function_exists('mb_convert_encoding')) {
            return mb_convert_encoding($value, 'UTF-8', $from);
        }

        $converted = iconv($from, 'UTF-8//IGNORE', $value);
        if (! is_string($converted)) {
            $this->fail(__('ui.messages.manuscript_file_encoding_invalid'));
        }

        return $converted;
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['manuscript_file' => [$message]]);
    }
}
