<?php

namespace App\Services;

class RichTextSanitizer
{
    public function clean(?string $html): string
    {
        $html = strip_tags((string) $html, '<p><br><strong><b><em><i><u><ul><ol><li>');
        return preg_replace('/<([a-z0-9]+)\b[^>]*>/i', '<$1>', $html) ?? '';
    }

    public function cleanPayload(array $payload): array
    {
        array_walk_recursive($payload, function (&$value, $key): void {
            if (str_ends_with((string) $key, '_apply_filters')) $value = (bool) $value;
            if (is_string($value) && str_ends_with((string) $key, '_html')) {
                $value = $this->clean($value);
            }
        });
        return $payload;
    }
}
