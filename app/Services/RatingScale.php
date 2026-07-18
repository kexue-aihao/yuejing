<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

final class RatingScale
{
    /**
     * Convert and validate the deliberately non-contiguous rating scale.
     * Values in the gaps are rejected instead of being silently reclassified.
     */
    public function normalize(mixed $value): float
    {
        if (! is_numeric($value) || ! preg_match('/^\d+(?:\.\d)?$/', (string) $value)) {
            throw ValidationException::withMessages(['rating' => __('reviews.invalid_precision')]);
        }

        $rating = round((float) $value, 1);
        $tenths = (int) round($rating * 10);

        if (! $this->isAllowedTenths($tenths)) {
            throw ValidationException::withMessages([
                'rating' => __('reviews.invalid_range'),
            ]);
        }

        return $tenths / 10;
    }

    public function key(mixed $value): string
    {
        $tenths = (int) round($this->normalize($value) * 10);

        return match (true) {
            $tenths <= 50 => 'standard',
            $tenths <= 70 => 'bronze',
            $tenths <= 90 => 'diamond',
            default => 'supreme_diamond',
        };
    }

    public function summary(mixed $value): array
    {
        $normalized = $this->normalize($value);

        return [
            'rating' => $normalized,
            'key' => $this->key($normalized),
        ];
    }

    private function isAllowedTenths(int $tenths): bool
    {
        return ($tenths >= 10 && $tenths <= 50)
            || ($tenths >= 60 && $tenths <= 70)
            || ($tenths >= 80 && $tenths <= 90)
            || ($tenths >= 91 && $tenths <= 99);
    }
}
