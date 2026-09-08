<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ImageFilters
{
    public function catalog(): array
    {
        return json_decode(file_get_contents(resource_path('data/image-filters.json')), true, 512, JSON_THROW_ON_ERROR);
    }

    public function validate(Request $request): array
    {
        $catalog = $this->catalog();
        $keys = array_column($catalog['sliders'], 'key');
        $rules = [
            'image_filters' => ['sometimes', 'array', 'max:1', function ($attribute, $value, $fail) {
                if (! is_array($value) || ! array_is_list($value)) $fail('Image filters must be an ordered list.');
            }],
            'image_filters.*' => ['array:id,values,overlay'],
            'image_filters.*.id' => ['required', Rule::in(array_column($catalog['presets'], 'id'))],
            'image_filters.*.values' => ['sometimes', 'array:'.implode(',', $keys)],
        ];
        foreach ($catalog['sliders'] as $slider) {
            $rules['image_filters.*.values.'.$slider['key']] = ['sometimes', 'numeric', 'between:'.$slider['min'].','.$slider['max']];
        }
        $prefix = 'image_filters.*.overlay';
        $rules[$prefix] = ['sometimes', 'array:'.implode(',', array_keys($catalog['overlay']['defaults']))];
        foreach (['type' => 'types', 'direction' => 'directions', 'blend' => 'blendModes'] as $key => $options) {
            $rules[$prefix.'.'.$key] = ['sometimes', Rule::in(array_keys($catalog['overlay'][$options]))];
        }
        foreach (['color1', 'color2'] as $key) $rules[$prefix.'.'.$key] = ['sometimes', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'];
        foreach ($catalog['overlay']['sliders'] as $slider) {
            $rules[$prefix.'.'.$slider['key']] = ['sometimes', 'numeric', 'between:'.$slider['min'].','.$slider['max']];
        }
        return $request->validate($rules);
    }

    public function operations(array $stack): array
    {
        $catalog = $this->catalog();
        $presets = array_column($catalog['presets'], null, 'id');
        $operations = [];
        foreach (array_slice($stack, 0, 1) as $filter) {
            $preset = $presets[$filter['id'] ?? ''] ?? null;
            if (! $preset) continue;
            foreach ($catalog['operationOrder'] as $key) {
                $slider = collect($catalog['sliders'])->firstWhere('key', $key);
                $key = $slider['key'];
                $value = (float) ($filter['values'][$key] ?? $preset['values'][$key] ?? $slider['default']);
                $value = max($slider['min'], min($slider['max'], $value));
                if ($value != $slider['default']) $operations[] = [$key, $value];
            }
        }
        return $operations;
    }

    public function overlay(array $stack): array
    {
        $catalog = $this->catalog();
        $filter = $stack[0] ?? null;
        $preset = collect($catalog['presets'])->firstWhere('id', $filter['id'] ?? null);
        return $preset ? array_replace($catalog['overlay']['defaults'], $preset['overlay'] ?? [], $filter['overlay'] ?? []) : $catalog['overlay']['defaults'];
    }

    // CSS Filter Effects matrices, applied in sRGB and clamped after each operation.
    public function render(string $bytes, array $stack): string
    {
        $image = imagecreatefromstring($bytes);
        abort_unless($image, 422, 'This image could not be prepared.');
        imagepalettetotruecolor($image);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        app(ImageOverlay::class)->apply($image, $this->overlay($stack));
        $all = $this->operations($stack);
        $operations = array_map(fn ($op) => $this->matrix(...$op), array_filter($all, fn ($op) => $op[0] !== 'blur'));
        $width = imagesx($image); $height = imagesy($image);
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $pixel = imagecolorat($image, $x, $y);
                $rgb = [($pixel >> 16) & 255, ($pixel >> 8) & 255, $pixel & 255];
                foreach ($operations as $m) {
                    [$r, $g, $b] = $rgb;
                    for ($i = 0; $i < 3; $i++) {
                        $rgb[$i] = max(0, min(255, $m[$i][0]*$r + $m[$i][1]*$g + $m[$i][2]*$b + $m[$i][3]));
                    }
                }
                imagesetpixel($image, $x, $y, ($pixel & 0x7f000000) | ((int) round($rgb[0]) << 16) | ((int) round($rgb[1]) << 8) | (int) round($rgb[2]));
            }
        }
        foreach ($all as [$key, $value]) {
            if ($key === 'blur') $image = app(ImageBlur::class)->apply($image, $value);
        }
        ob_start(); imagepng($image); $result = ob_get_clean(); imagedestroy($image);
        return $result;
    }

    private function matrix(string $key, float $v): array
    {
        if ($key === 'invert') { $a = 1-2*$v; $b = 255*$v; return [[$a,0,0,$b],[0,$a,0,$b],[0,0,$a,$b]]; }
        if ($key === 'brightness') return [[$v,0,0,0],[0,$v,0,0],[0,0,$v,0]];
        if ($key === 'contrast') { $offset = 255*(.5-.5*$v); return [[$v,0,0,$offset],[0,$v,0,$offset],[0,0,$v,$offset]]; }
        if ($key === 'sepia') return [[1-.607*$v,.769*$v,.189*$v,0],[.349*$v,1-.314*$v,.168*$v,0],[.272*$v,.534*$v,1-.869*$v,0]];
        if ($key === 'grayscale') return [[1-.7874*$v,.7152*$v,.0722*$v,0],[.2126*$v,1-.2848*$v,.0722*$v,0],[.2126*$v,.7152*$v,1-.9278*$v,0]];
        if ($key === 'hue-rotate') {
            $c = cos(deg2rad($v)); $s = sin(deg2rad($v));
            return [[.213+.787*$c-.213*$s,.715-.715*$c-.715*$s,.072-.072*$c+.928*$s,0],
                [.213-.213*$c+.143*$s,.715+.285*$c+.140*$s,.072-.072*$c-.283*$s,0],
                [.213-.213*$c-.787*$s,.715-.715*$c+.715*$s,.072+.928*$c+.072*$s,0]];
        }
        $s = $key === 'grayscale' ? 1-$v : $v;
        return [[.213+.787*$s,.715-.715*$s,.072-.072*$s,0],[.213-.213*$s,.715+.285*$s,.072-.072*$s,0],[.213-.213*$s,.715-.715*$s,.072+.928*$s,0]];
    }
}
