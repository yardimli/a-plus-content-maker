<?php

namespace App\Services;

class ImageOverlay
{
    public function apply(\GdImage $image, array $o): void
    {
        if ($o['type'] === 'none' || $o['opacity'] == 0) return;
        $w = imagesx($image); $h = imagesy($image);
        $first = $this->rgb($o['color1']); $second = $this->rgb($o['color2']);
        $a1 = (float) $o['alpha1']; $a2 = (float) $o['alpha2'];
        $start = $o['stop1']/100; $end = max($start, $o['stop2']/100);
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $u = ($x+.5)/$w; $v = ($y+.5)/$h;
                $position = $o['type'] === 'radial' ? hypot($u-.5, $v-.5)/sqrt(.5) : match ($o['direction']) {
                    'to top' => 1-$v, 'to right' => $u, 'to left' => 1-$u,
                    'to bottom right' => ($u+$v)/2, 'to bottom left' => (1-$u+$v)/2,
                    'to top right' => ($u+1-$v)/2, 'to top left' => (2-$u-$v)/2,
                    default => $v,
                };
                $t = $o['type'] === 'solid' ? 0 : ($end === $start ? ($position >= $end ? 1 : 0) : max(0, min(1, ($position-$start)/($end-$start))));
                $alpha = (1-$t)*$a1 + $t*$a2;
                if ($alpha <= 0) continue;
                // CSS gradients interpolate premultiplied sRGB colors.
                $source = [];
                for ($i = 0; $i < 3; $i++) $source[$i] = ((1-$t)*$a1*$first[$i] + $t*$a2*$second[$i])/$alpha;
                $alpha *= $o['opacity'];
                $pixel = imagecolorat($image, $x, $y);
                $back = [(($pixel >> 16)&255)/255, (($pixel >> 8)&255)/255, ($pixel&255)/255];
                $ba = 1-(($pixel >> 24)&127)/127;
                $outAlpha = $alpha + $ba*(1-$alpha);
                $blend = $this->blend($back, $source, $o['blend']);
                $out = [];
                for ($i = 0; $i < 3; $i++) {
                    $out[$i] = (int) round(255*max(0, min(1, ($alpha*((1-$ba)*$source[$i]+$ba*$blend[$i]) + (1-$alpha)*$ba*$back[$i])/$outAlpha)));
                }
                imagesetpixel($image, $x, $y, ((int) round((1-$outAlpha)*127) << 24) | ($out[0] << 16) | ($out[1] << 8) | $out[2]);
            }
        }
    }

    private function rgb(string $hex): array
    {
        return array_map(fn ($part) => hexdec($part)/255, str_split(substr($hex, 1), 2));
    }

    // W3C Compositing and Blending Level 1, including non-separable modes.
    public function blend(array $back, array $source, string $mode): array
    {
        $sat = fn ($c) => max($c)-min($c);
        $lum = fn ($c) => .3*$c[0]+.59*$c[1]+.11*$c[2];
        if ($mode === 'hue') return $this->setLum($this->setSat($source, $sat($back)), $lum($back));
        if ($mode === 'saturation') return $this->setLum($this->setSat($back, $sat($source)), $lum($back));
        if ($mode === 'color') return $this->setLum($source, $lum($back));
        if ($mode === 'luminosity') return $this->setLum($back, $lum($source));
        $result = [];
        foreach ($back as $i => $b) {
            $s = $source[$i];
            $result[] = match ($mode) {
                'multiply' => $b*$s,
                'screen' => $b+$s-$b*$s,
                'overlay' => $b <= .5 ? 2*$b*$s : 1-2*(1-$b)*(1-$s),
                'darken' => min($b, $s), 'lighten' => max($b, $s),
                'color-dodge' => $b == 0 ? 0 : ($s == 1 ? 1 : min(1, $b/(1-$s))),
                'color-burn' => $b == 1 ? 1 : ($s == 0 ? 0 : 1-min(1, (1-$b)/$s)),
                'hard-light' => $s <= .5 ? 2*$b*$s : 1-2*(1-$b)*(1-$s),
                'soft-light' => $s <= .5 ? $b-(1-2*$s)*$b*(1-$b) : $b+(2*$s-1)*(($b <= .25 ? ((16*$b-12)*$b+4)*$b : sqrt($b))-$b),
                'difference' => abs($b-$s), 'exclusion' => $b+$s-2*$b*$s,
                default => $s, // normal and CSS-wide keywords in our isolated image context
            };
        }
        return $result;
    }

    private function setSat(array $c, float $s): array
    {
        $sorted = $c; asort($sorted); [$low, $mid, $high] = array_keys($sorted);
        if ($c[$high] > $c[$low]) {
            $c[$mid] = ($c[$mid]-$c[$low])*$s/($c[$high]-$c[$low]); $c[$high] = $s;
        } else { $c[$mid] = $c[$high] = 0; }
        $c[$low] = 0;
        return $c;
    }

    private function setLum(array $c, float $l): array
    {
        $d = $l-(.3*$c[0]+.59*$c[1]+.11*$c[2]);
        $c = array_map(fn ($v) => $v+$d, $c);
        $n = min($c); $x = max($c);
        if ($n < 0) $c = array_map(fn ($v) => $l+($v-$l)*$l/($l-$n), $c);
        if ($x > 1) $c = array_map(fn ($v) => $l+($v-$l)*(1-$l)/($x-$l), $c);
        return $c;
    }
}
