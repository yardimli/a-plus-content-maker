<?php

declare(strict_types=1);

$publicRoot = dirname(__DIR__).'/public';
$templates = [
    ['slug' => 'letters-at-low-tide', 'image' => 'romance-letters-low-tide.png', 'series' => false],
    ['slug' => 'the-cinnamon-bookshop', 'image' => 'romance-cinnamon-bookshop.png', 'series' => false],
    ['slug' => 'hearts-of-hawthorne-bay', 'image' => 'romance-hawthorne-bay-series.png', 'series' => true],
    ['slug' => 'orbit-of-ash', 'image' => 'scifi-orbit-of-ash.png', 'series' => false],
    ['slug' => 'the-memory-cartographer', 'image' => 'scifi-memory-cartographer.png', 'series' => false],
    ['slug' => 'the-meridian-expanse', 'image' => 'scifi-meridian-expanse-series.png', 'series' => true],
    ['slug' => 'a-crown-of-briars', 'image' => 'fantasy-crown-of-briars.png', 'series' => false],
    ['slug' => 'the-mapmakers-dragon', 'image' => 'fantasy-mapmakers-dragon.png', 'series' => false],
    ['slug' => 'chronicles-of-emberfall', 'image' => 'fantasy-emberfall-series.png', 'series' => true],
    ['slug' => 'the-black-harbor', 'image' => 'thriller-black-harbor.png', 'series' => false],
    ['slug' => 'zero-hour-witness', 'image' => 'thriller-zero-hour-witness.png', 'series' => false],
    ['slug' => 'the-rook-directive', 'image' => 'thriller-rook-directive-series.png', 'series' => true],
];

function loadPng(string $path): \GdImage
{
    $image = imagecreatefrompng($path);

    if (! $image instanceof \GdImage) {
        throw new RuntimeException('Unable to read '.$path);
    }

    return $image;
}

/** Center-crop generated artwork without distorting it. */
function cropArtworkToWebp(string $sourcePath, string $targetPath, int $targetWidth, int $targetHeight): void
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
    imagewebp($canvas, $targetPath, 88);
    imagedestroy($canvas);
    imagedestroy($source);
}

function cropRegion(\GdImage $source, float $x, float $y, float $width, float $height): \GdImage
{
    $sourceWidth = imagesx($source);
    $sourceHeight = imagesy($source);
    $sourceX = max(0, (int) round($sourceWidth * $x));
    $sourceY = max(0, (int) round($sourceHeight * $y));
    $cropWidth = min($sourceWidth - $sourceX, max(1, (int) round($sourceWidth * $width)));
    $cropHeight = min($sourceHeight - $sourceY, max(1, (int) round($sourceHeight * $height)));
    $crop = imagecreatetruecolor($cropWidth, $cropHeight);
    imagecopy($crop, $source, 0, 0, $sourceX, $sourceY, $cropWidth, $cropHeight);

    return $crop;
}

/** Keep the complete book mockup visible and surround it with a clean white background. */
function bookMockupToWebp(\GdImage $book, string $targetPath, int $targetWidth, int $targetHeight): void
{
    $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
    $white = imagecolorallocate($canvas, 255, 255, 255);
    imagefill($canvas, 0, 0, $white);

    $padding = max(6, (int) round(min($targetWidth, $targetHeight) * .04));
    $availableWidth = $targetWidth - ($padding * 2);
    $availableHeight = $targetHeight - ($padding * 2);
    $scale = min($availableWidth / imagesx($book), $availableHeight / imagesy($book));
    $renderWidth = max(1, (int) round(imagesx($book) * $scale));
    $renderHeight = max(1, (int) round(imagesy($book) * $scale));
    $targetX = (int) floor(($targetWidth - $renderWidth) / 2);
    $targetY = (int) floor(($targetHeight - $renderHeight) / 2);

    imagecopyresampled($canvas, $book, $targetX, $targetY, 0, 0, $renderWidth, $renderHeight, imagesx($book), imagesy($book));
    imagewebp($canvas, $targetPath, 90);
    imagedestroy($canvas);
}

foreach ($templates as $template) {
    $galleryPath = $publicRoot.'/images/templates/'.$template['image'];
    $sourceDirectory = $publicRoot.'/images/templates/generated-sources/'.$template['slug'];
    $targetDirectory = $publicRoot.'/images/templates/blocks/'.$template['slug'];

    if (! is_dir($targetDirectory) && ! mkdir($targetDirectory, 0775, true) && ! is_dir($targetDirectory)) {
        throw new RuntimeException('Unable to create '.$targetDirectory);
    }

    cropArtworkToWebp($sourceDirectory.'/hero.png', $targetDirectory.'/hero.webp', 970, 300);

    foreach ([1, 2, 3] as $feature) {
        cropArtworkToWebp(
            $sourceDirectory.'/feature-'.$feature.'.png',
            $targetDirectory.'/feature-'.$feature.'.webp',
            300,
            300,
        );
    }

    $gallery = loadPng($galleryPath);

    if ($template['series']) {
        foreach ([.02, .20, .38] as $index => $x) {
            $book = cropRegion($gallery, $x, .20, .18, .62);
            bookMockupToWebp($book, $targetDirectory.'/book-'.($index + 1).'.webp', 150, 300);

            if ($index === 0) {
                bookMockupToWebp($book, $targetDirectory.'/cover.webp', 300, 300);
            }

            imagedestroy($book);
        }
    } else {
        $book = cropRegion($gallery, .025, .05, .35, .88);
        bookMockupToWebp($book, $targetDirectory.'/cover.webp', 300, 300);
        imagedestroy($book);
    }

    imagedestroy($gallery);
}

echo 'Generated exact-fit, text-free block artwork for '.count($templates)." templates.\n";
