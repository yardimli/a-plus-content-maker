<?php

declare(strict_types=1);

$publicRoot = dirname(__DIR__).'/public';
$templates = [
    ['slug' => 'letters-at-low-tide', 'series' => false],
    ['slug' => 'the-cinnamon-bookshop', 'series' => false],
    ['slug' => 'hearts-of-hawthorne-bay', 'series' => true],
    ['slug' => 'orbit-of-ash', 'series' => false],
    ['slug' => 'the-memory-cartographer', 'series' => false],
    ['slug' => 'the-meridian-expanse', 'series' => true],
    ['slug' => 'a-crown-of-briars', 'series' => false],
    ['slug' => 'the-mapmakers-dragon', 'series' => false],
    ['slug' => 'chronicles-of-emberfall', 'series' => true],
    ['slug' => 'the-black-harbor', 'series' => false],
    ['slug' => 'zero-hour-witness', 'series' => false],
    ['slug' => 'the-rook-directive', 'series' => true],
];

function loadPng(string $path): \GdImage
{
    $image = imagecreatefrompng($path);

    if (! $image instanceof \GdImage) {
        throw new RuntimeException('Unable to read '.$path);
    }

    return $image;
}

/** Center-crop generated scene artwork without distortion. */
function cropArtworkToWebp(string $sourcePath, string $targetPath, int $targetWidth, int $targetHeight, int $quality = 96): void
{
    $source = loadPng($sourcePath);
    $sourceWidth = imagesx($source);
    $sourceHeight = imagesy($source);
    $sourceRatio = $sourceWidth / $sourceHeight;
    $targetRatio = $targetWidth / $targetHeight;

    if ($sourceRatio > $targetRatio) {
        $cropHeight = $sourceHeight;
        $cropWidth = (int) round($sourceHeight * $targetRatio);
        $sourceX = (int) floor(($sourceWidth - $cropWidth) / 2);
        $sourceY = 0;
    } else {
        $cropWidth = $sourceWidth;
        $cropHeight = (int) round($sourceWidth / $targetRatio);
        $sourceX = 0;
        $sourceY = (int) floor(($sourceHeight - $cropHeight) / 2);
    }

    $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
    imagecopyresampled($canvas, $source, 0, 0, $sourceX, $sourceY, $targetWidth, $targetHeight, $cropWidth, $cropHeight);
    imagewebp($canvas, $targetPath, $quality);
    imagedestroy($canvas);
    imagedestroy($source);
}

/** Resize a square catalog mockup without cropping its white background or hardcover edges. */
function resizeMockupToWebp(string $sourcePath, string $targetPath, int $targetWidth, int $targetHeight): void
{
    $source = loadPng($sourcePath);
    $sourceWidth = imagesx($source);
    $sourceHeight = imagesy($source);
    $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
    $white = imagecolorallocate($canvas, 255, 255, 255);
    imagefill($canvas, 0, 0, $white);

    $scale = min($targetWidth / $sourceWidth, $targetHeight / $sourceHeight);
    $renderWidth = max(1, (int) round($sourceWidth * $scale));
    $renderHeight = max(1, (int) round($sourceHeight * $scale));
    $targetX = (int) floor(($targetWidth - $renderWidth) / 2);
    $targetY = (int) floor(($targetHeight - $renderHeight) / 2);
    imagecopyresampled($canvas, $source, $targetX, $targetY, 0, 0, $renderWidth, $renderHeight, $sourceWidth, $sourceHeight);
    imagewebp($canvas, $targetPath, 100);
    imagedestroy($canvas);
    imagedestroy($source);
}

/** Crop only excess white canvas to a 2:3 product slot; never crop the 6x9 hardcover itself. */
function portraitMockupToWebp(string $sourcePath, string $targetPath): void
{
    $source = loadPng($sourcePath);
    $sourceWidth = imagesx($source);
    $sourceHeight = imagesy($source);
    $cropWidth = min($sourceWidth, (int) round($sourceHeight * (2 / 3)));
    $sourceX = (int) floor(($sourceWidth - $cropWidth) / 2);
    $canvas = imagecreatetruecolor(200, 300);
    $white = imagecolorallocate($canvas, 255, 255, 255);
    imagefill($canvas, 0, 0, $white);
    imagecopyresampled($canvas, $source, 0, 0, $sourceX, 0, 200, 300, $cropWidth, $sourceHeight);
    imagewebp($canvas, $targetPath, 100);
    imagedestroy($canvas);
    imagedestroy($source);
}

foreach ($templates as $template) {
    $artworkDirectory = $publicRoot.'/images/templates/generated-sources/'.$template['slug'];
    $mockupDirectory = $publicRoot.'/images/templates/cover-mockups/'.$template['slug'];
    $targetDirectory = $publicRoot.'/images/templates/blocks/'.$template['slug'];

    if (! is_dir($targetDirectory) && ! mkdir($targetDirectory, 0775, true) && ! is_dir($targetDirectory)) {
        throw new RuntimeException('Unable to create '.$targetDirectory);
    }

    cropArtworkToWebp($artworkDirectory.'/hero.png', $targetDirectory.'/hero.webp', 970, 300);

    foreach ([1, 2, 3] as $feature) {
        cropArtworkToWebp(
            $artworkDirectory.'/feature-'.$feature.'.png',
            $targetDirectory.'/feature-'.$feature.'.webp',
            300,
            300,
        );
    }

    if ($template['series']) {
        foreach ([1, 2, 3] as $book) {
            portraitMockupToWebp(
                $mockupDirectory.'/book-'.$book.'.png',
                $targetDirectory.'/book-'.$book.'.webp',
            );
        }

        resizeMockupToWebp($mockupDirectory.'/book-1.png', $targetDirectory.'/cover.webp', 300, 300);
    } else {
        resizeMockupToWebp($mockupDirectory.'/cover.png', $targetDirectory.'/cover.webp', 300, 300);
    }
}

echo 'Generated high-quality block artwork and standalone hardcover mockups for '.count($templates)." templates.\n";
