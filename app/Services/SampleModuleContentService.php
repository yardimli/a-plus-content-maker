<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\Project;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SampleModuleContentService
{
    private array $assetIds = [];

    public function __construct(private readonly ModuleRegistry $registry)
    {
    }

    public function build(Project $project, string $type, int $userId): array
    {
        $this->assetIds = [];
        $image = fn (string $path): int => $this->asset($project, $userId, $path);
        $coverOne = '/images/templates/blocks/a-crown-of-briars/cover.webp';
        $coverTwo = '/images/templates/blocks/orbit-of-ash/cover.webp';
        $coverThree = '/images/templates/blocks/letters-at-low-tide/cover.webp';
        $heroFantasy = '/images/templates/blocks/a-crown-of-briars/hero.webp';
        $heroRomance = '/images/templates/blocks/letters-at-low-tide/hero.webp';
        $featureOne = '/images/templates/blocks/a-crown-of-briars/feature-1.webp';
        $featureTwo = '/images/templates/blocks/a-crown-of-briars/feature-2.webp';
        $featureThree = '/images/templates/blocks/a-crown-of-briars/feature-3.webp';

        $body = '<p>Step into an immersive story of impossible choices, unforgettable characters, and a world where every secret carries a price.</p>';
        $short = '<p>Rich atmosphere, vivid characters, and stakes that keep rising until the final page.</p>';

        return match ($type) {
            'company_logo' => ['image' => $image('/images/brand/favicon-master.png')],
            'comparison_chart' => [
                'show_reviews' => true, 'show_prices' => true, 'show_add_to_cart' => false,
                'products' => [
                    ['asin' => 'B000000001', 'image' => $image($coverOne), 'title' => 'A Crown of Briars', 'highlighted' => true],
                    ['asin' => 'B000000002', 'image' => $image($coverTwo), 'title' => 'Orbit of Ash', 'highlighted' => false],
                    ['asin' => 'B000000003', 'image' => $image($coverThree), 'title' => 'Letters at Low Tide', 'highlighted' => false],
                ],
                'metrics' => [
                    ['label' => 'Genre', 'values' => 'Epic fantasy|Science fiction|Romance'],
                    ['label' => 'Reading experience', 'values' => 'Sweeping|Cinematic|Emotional'],
                    ['label' => 'Series', 'values' => 'Book one|Standalone|Standalone'],
                ],
            ],
            'four_image_text' => ['headline' => 'Four reasons to enter the story', 'items' => [
                ['image' => $image($featureOne), 'headline' => 'A vivid world', 'body_html' => $short],
                ['image' => $image($featureTwo), 'headline' => 'Unlikely allies', 'body_html' => $short],
                ['image' => $image($featureThree), 'headline' => 'Impossible stakes', 'body_html' => $short],
                ['image' => $image($coverTwo), 'headline' => 'A bold adventure', 'body_html' => $short],
            ]],
            'four_image_quadrant' => ['items' => [
                ['image' => $image($featureOne), 'headline' => 'The hidden kingdom', 'body_html' => $short],
                ['image' => $image($featureTwo), 'headline' => 'The reluctant heir', 'body_html' => $short],
                ['image' => $image($featureThree), 'headline' => 'The ancient oath', 'body_html' => $short],
                ['image' => $image($coverThree), 'headline' => 'The final promise', 'body_html' => $short],
            ]],
            'dark_text_overlay' => ['background' => $image($heroFantasy), 'headline' => 'A kingdom on the brink', 'body_html' => '<p>Magic has a price. Destiny has other plans.</p>'],
            'light_text_overlay' => ['background' => $image($heroRomance), 'headline' => 'Some shores call us home', 'body_html' => '<p>A sweeping story of hope, memory, and second chances.</p>'],
            'image_header_text' => ['top_headline' => 'Discover the world beyond the cover', 'image' => $image($heroRomance), 'headline' => 'A story worth remembering', 'body_html' => $body],
            'multiple_image_a' => ['headline' => 'Choose your path', 'description_html' => $body, 'items' => [
                ['image' => $image($coverOne), 'caption' => 'The kingdom'], ['image' => $image($featureOne), 'caption' => 'The quest'],
                ['image' => $image($featureTwo), 'caption' => 'The allies'], ['image' => $image($featureThree), 'caption' => 'The reckoning'],
            ]],
            'product_description_text' => ['body_html' => '<p><strong>An unforgettable reading experience.</strong></p>'.$body.'<p>Perfect for readers who love atmospheric worlds, layered mysteries, and heroes who must decide what they are willing to risk.</p>'],
            'single_image_highlights' => ['image' => $image($coverOne), 'highlights_headline' => 'Inside the story', 'sections' => [
                ['subheadline' => 'A world in shadow', 'body_html' => $short], ['subheadline' => 'A dangerous alliance', 'body_html' => $short], ['subheadline' => 'A choice that changes everything', 'body_html' => $short],
            ], 'bullets' => [['text' => 'Immersive worldbuilding'], ['text' => 'Found family'], ['text' => 'High-stakes adventure']]],
            'single_image_sidebar' => [
                'primary_image' => $image($coverThree), 'image_caption' => 'A story of second chances', 'headline' => 'When the past returns with the tide',
                'subheadline' => 'One summer. One unopened letter.', 'body_html' => $body, 'sidebar_image' => $image($featureThree),
                'sidebar_headline' => 'Perfect for book clubs', 'sidebar_body_html' => $short, 'bullets' => [['text' => 'Coastal setting'], ['text' => 'Slow-burn romance']],
            ],
            'single_image_specs_detail' => ['headline' => 'The story at a glance', 'image' => $image($coverTwo), 'sections' => [
                ['headline' => 'The mission', 'subheadline' => 'Cross the silent frontier', 'body_html' => $short],
                ['headline' => 'The crew', 'subheadline' => 'Strangers bound by survival', 'body_html' => $short],
                ['headline' => 'The threat', 'subheadline' => 'Something is waiting', 'body_html' => $short],
            ]],
            'single_left_image' => ['image' => $image($coverThree), 'headline' => 'A love that rewrites everything', 'body_html' => $body],
            'single_right_image' => ['headline' => 'The journey starts here', 'body_html' => $body, 'image' => $image($coverTwo)],
            'technical_specifications' => ['headline' => 'Book details', 'columns' => '2', 'specifications' => [
                ['specification' => 'Genre', 'definition' => 'Epic fantasy adventure'], ['specification' => 'Reading order', 'definition' => 'Book one of a new series'],
                ['specification' => 'Setting', 'definition' => 'A divided kingdom'], ['specification' => 'Themes', 'definition' => 'Courage, loyalty, and sacrifice'],
            ]],
            'standard_text' => ['headline' => 'A story readers will carry with them', 'body_html' => $body.'<p>Begin the journey today.</p>'],
            'three_images_text' => ['headline' => 'Three reasons to begin', 'items' => [
                ['image' => $image($featureOne), 'headline' => 'A world to explore', 'body_html' => $short],
                ['image' => $image($featureTwo), 'headline' => 'Heroes to follow', 'body_html' => $short],
                ['image' => $image($featureThree), 'headline' => 'Secrets to uncover', 'body_html' => $short],
            ]],
            default => $this->registry->defaults($type),
        };
    }

    private function asset(Project $project, int $userId, string $publicPath): int
    {
        if (isset($this->assetIds[$publicPath])) {
            return $this->assetIds[$publicPath];
        }

        $existing = $project->assets()->where('metadata->sample_path', $publicPath)->first();
        if ($existing) {
            return $this->assetIds[$publicPath] = $existing->id;
        }

        $realSource = realpath(public_path(ltrim($publicPath, '/')));
        abort_unless($realSource && is_file($realSource), 422, 'Sample image is unavailable.');
        $dimensions = getimagesize($realSource);
        abort_unless($dimensions !== false, 422, 'Sample image is invalid.');
        $extension = strtolower(pathinfo($realSource, PATHINFO_EXTENSION));
        $path = 'projects/'.$project->uuid.'/sample-'.Str::uuid().'.'.$extension;
        Storage::disk('public')->put($path, file_get_contents($realSource));
        $asset = Asset::create([
            'user_id' => $userId, 'project_id' => $project->id, 'source' => 'sample', 'disk' => 'public', 'path' => $path,
            'original_name' => basename($realSource), 'mime_type' => $dimensions['mime'], 'extension' => $extension,
            'size_bytes' => filesize($realSource), 'width' => $dimensions[0], 'height' => $dimensions[1],
            'alt_text' => Str::headline(pathinfo($realSource, PATHINFO_FILENAME)).' book campaign artwork',
            'checksum' => hash_file('sha256', $realSource), 'metadata' => ['sample_path' => $publicPath],
        ]);

        return $this->assetIds[$publicPath] = $asset->id;
    }
}
