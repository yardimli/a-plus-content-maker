<?php

namespace App\Services;

class ImageBlur
{
    // Three separable box passes approximate Gaussian blur in linear time.
    // Premultiplied alpha prevents dark fringes around transparent pixels.
    public function apply(\GdImage $image, float $sigma): \GdImage
    {
        if ($sigma <= 0) return $image;
        if ($sigma < 1) {
            $radius = (int) ceil(3*$sigma); $kernel = [];
            for ($i = -$radius; $i <= $radius; $i++) $kernel[$i] = exp(-$i*$i/(2*$sigma*$sigma));
            $total = array_sum($kernel);
            $kernel = array_map(fn ($v) => $v/$total, $kernel);
            foreach ([false, true] as $vertical) {
                $next = $this->smallPass($image, $kernel, $vertical);
                imagedestroy($image); $image = $next;
            }
            return $image;
        }
        $low = (int) floor(sqrt(4*$sigma*$sigma+1));
        if ($low % 2 === 0) $low--;
        $low = max(1, $low); $high = $low+2;
        $count = (int) round((12*$sigma*$sigma-3*$low*$low-12*$low-9)/(-4*$low-4));
        for ($i = 0; $i < 3; $i++) {
            $radius = (int) (($i < $count ? $low : $high)-1)/2;
            if ($radius < 1) continue;
            foreach ([false, true] as $vertical) {
                $next = $this->pass($image, (int) $radius, $vertical);
                imagedestroy($image); $image = $next;
            }
        }
        return $image;
    }

    private function smallPass(\GdImage $image, array $kernel, bool $vertical): \GdImage
    {
        $w = imagesx($image); $h = imagesy($image);
        $out = imagecreatetruecolor($w, $h); imagealphablending($out, false); imagesavealpha($out, true);
        for ($y = 0; $y < $h; $y++) for ($x = 0; $x < $w; $x++) {
            $sum = [0,0,0,0];
            foreach ($kernel as $offset => $weight) {
                $sx = $vertical ? $x : $x+$offset; $sy = $vertical ? $y+$offset : $y;
                if ($sx < 0 || $sx >= $w || $sy < 0 || $sy >= $h) continue;
                $p = imagecolorat($image, $sx, $sy); $a = (1-(($p >> 24)&127)/127)*$weight;
                $sum[0] += (($p >> 16)&255)*$a; $sum[1] += (($p >> 8)&255)*$a;
                $sum[2] += ($p&255)*$a; $sum[3] += $a;
            }
            $pixel = (int) round((1-max(0, min(1, $sum[3])))*127) << 24;
            for ($i = 0; $i < 3; $i++) $pixel |= (int) round(max(0, min(255, $sum[3] > .000001 ? $sum[$i]/$sum[3] : 0))) << (16-8*$i);
            imagesetpixel($out, $x, $y, $pixel);
        }
        return $out;
    }

    private function pass(\GdImage $image, int $radius, bool $vertical): \GdImage
    {
        $w = imagesx($image); $h = imagesy($image);
        $output = imagecreatetruecolor($w, $h); imagealphablending($output, false); imagesavealpha($output, true);
        $length = $vertical ? $h : $w; $lines = $vertical ? $w : $h; $size = 2*$radius+1;
        for ($line = 0; $line < $lines; $line++) {
            $sum = [0,0,0,0];
            $accumulate = function ($position, $sign) use ($image, $length, $line, $vertical, &$sum) {
                if ($position < 0 || $position >= $length) return;
                $p = imagecolorat($image, $vertical ? $line : $position, $vertical ? $position : $line);
                $a = 1-(($p >> 24)&127)/127;
                $sum[0] += $sign*(($p >> 16)&255)*$a; $sum[1] += $sign*(($p >> 8)&255)*$a;
                $sum[2] += $sign*($p&255)*$a; $sum[3] += $sign*$a;
            };
            for ($p = 0; $p <= $radius; $p++) $accumulate($p, 1);
            for ($p = 0; $p < $length; $p++) {
                $alpha = max(0, min(1, $sum[3]/$size));
                $pixel = (int) round((1-$alpha)*127) << 24;
                for ($i = 0; $i < 3; $i++) $pixel |= (int) round(max(0, min(255, $sum[3] > .000001 ? $sum[$i]/$sum[3] : 0))) << (16-8*$i);
                imagesetpixel($output, $vertical ? $line : $p, $vertical ? $p : $line, $pixel);
                $accumulate($p-$radius, -1); $accumulate($p+$radius+1, 1);
            }
        }
        return $output;
    }
}
