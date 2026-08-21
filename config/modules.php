<?php

$headline = ['key' => 'headline', 'label' => 'Headline', 'type' => 'text'];
$subheadline = ['key' => 'subheadline', 'label' => 'Subheadline', 'type' => 'text'];
$body = ['key' => 'body_html', 'label' => 'Body text', 'type' => 'richtext'];
$image300 = ['key' => 'image', 'label' => 'Image', 'type' => 'image', 'width' => 300, 'height' => 300, 'required' => true];
$standardItem = [$image300, $headline, $body];

return [
    'company_logo' => [
        'name' => 'Standard Company Logo', 'description' => 'A wide author or publisher brand mark.', 'ai_ready' => false, 'category' => 'Brand',
        'fields' => [['key' => 'image', 'label' => 'Logo image', 'type' => 'image', 'width' => 600, 'height' => 180, 'required' => true]],
    ],
    'comparison_chart' => [
        'name' => 'Standard Comparison Chart', 'description' => 'Compare books, editions, or titles in a series.', 'ai_ready' => false, 'category' => 'Comparison',
        'fields' => [
            ['key' => 'show_reviews', 'label' => 'Show reviews', 'type' => 'checkbox', 'default' => true],
            ['key' => 'show_prices', 'label' => 'Show prices', 'type' => 'checkbox', 'default' => true],
            ['key' => 'show_add_to_cart', 'label' => 'Show add to cart', 'type' => 'checkbox', 'default' => false],
        ],
        'repeaters' => [
            ['key' => 'products', 'label' => 'Comparison products', 'min' => 2, 'max' => 6, 'fields' => [
                ['key' => 'asin', 'label' => 'ASIN', 'type' => 'asin', 'required' => true],
                ['key' => 'image', 'label' => 'Product image', 'type' => 'image', 'width' => 200, 'height' => 300, 'required' => true],
                ['key' => 'title', 'label' => 'Title', 'type' => 'text', 'required' => true],
                ['key' => 'highlighted', 'label' => 'Highlight column', 'type' => 'checkbox'],
            ]],
            ['key' => 'metrics', 'label' => 'Comparison metrics', 'min' => 1, 'max' => 10, 'fields' => [
                ['key' => 'label', 'label' => 'Metric', 'type' => 'text', 'required' => true],
                ['key' => 'values', 'label' => 'Values (separate with |)', 'type' => 'text'],
            ]],
        ],
    ],
    'four_image_text' => [
        'name' => 'Standard Four Image & Text', 'description' => 'Four feature columns beneath a shared headline.', 'ai_ready' => true, 'category' => 'Images & text',
        'fields' => [$headline], 'repeaters' => [['key' => 'items', 'label' => 'Feature', 'min' => 4, 'max' => 4, 'fields' => [
            ['key' => 'image', 'label' => 'Image', 'type' => 'image', 'width' => 220, 'height' => 220, 'required' => true], $headline, $body,
        ]]],
    ],
    'four_image_quadrant' => [
        'name' => 'Standard Four Image/Text Quadrant', 'description' => 'A compact two-by-two feature grid.', 'ai_ready' => true, 'category' => 'Images & text',
        'fields' => [], 'repeaters' => [['key' => 'items', 'label' => 'Quadrant', 'min' => 4, 'max' => 4, 'fields' => [
            ['key' => 'image', 'label' => 'Image', 'type' => 'image', 'width' => 135, 'height' => 135, 'required' => true], array_merge($headline, ['required' => true]), array_merge($body, ['required' => true]),
        ]]],
    ],
    'dark_text_overlay' => [
        'name' => 'Standard Image & Dark Text Overlay', 'description' => 'Dark copy over a panoramic background.', 'ai_ready' => true, 'category' => 'Hero',
        'fields' => [['key' => 'background', 'label' => 'Background image', 'type' => 'image', 'width' => 970, 'height' => 300, 'required' => true], $headline, $body],
    ],
    'light_text_overlay' => [
        'name' => 'Standard Image & Light Text Overlay', 'description' => 'Light copy over a panoramic background.', 'ai_ready' => true, 'category' => 'Hero',
        'fields' => [['key' => 'background', 'label' => 'Background image', 'type' => 'image', 'width' => 970, 'height' => 300, 'required' => true], $headline, $body],
    ],
    'image_header_text' => [
        'name' => 'Standard Image Header With Text', 'description' => 'A large image header followed by supporting copy.', 'ai_ready' => false, 'category' => 'Hero',
        'fields' => [
            ['key' => 'top_headline', 'label' => 'Top headline', 'type' => 'text'],
            ['key' => 'image', 'label' => 'Header image', 'type' => 'image', 'width' => 970, 'height' => 600, 'required' => true],
            $headline, $body,
        ],
    ],
    'multiple_image_a' => [
        'name' => 'Standard Multiple Image Module A', 'description' => 'A selectable four-image story with headline and description.', 'ai_ready' => true, 'category' => 'Images & text',
        'fields' => [$headline, ['key' => 'description_html', 'label' => 'Description', 'type' => 'richtext']],
        'repeaters' => [['key' => 'items', 'label' => 'Image', 'min' => 1, 'max' => 4, 'fields' => [$image300, ['key' => 'caption', 'label' => 'Image caption', 'type' => 'text']]]],
    ],
    'product_description_text' => [
        'name' => 'Standard Product Description Text', 'description' => 'A full-width long-form description.', 'ai_ready' => false, 'category' => 'Text',
        'fields' => [array_merge($body, ['required' => true])],
    ],
    'single_image_highlights' => [
        'name' => 'Standard Single Image & Highlights', 'description' => 'One image, three narrative sections, and highlight bullets.', 'ai_ready' => true, 'category' => 'Images & text',
        'fields' => [$image300, ['key' => 'highlights_headline', 'label' => 'Highlights headline', 'type' => 'text']],
        'repeaters' => [
            ['key' => 'sections', 'label' => 'Story section', 'min' => 3, 'max' => 3, 'fields' => [$subheadline, $body]],
            ['key' => 'bullets', 'label' => 'Highlight', 'min' => 1, 'max' => 6, 'fields' => [['key' => 'text', 'label' => 'Bullet point', 'type' => 'text']]],
        ],
    ],
    'single_image_sidebar' => [
        'name' => 'Standard Single Image & Sidebar', 'description' => 'A main story with portrait art and a supporting sidebar.', 'ai_ready' => true, 'category' => 'Images & text',
        'fields' => [
            ['key' => 'primary_image', 'label' => 'Primary image', 'type' => 'image', 'width' => 300, 'height' => 400, 'required' => true],
            ['key' => 'image_caption', 'label' => 'Image caption', 'type' => 'text'], $headline, $subheadline, $body,
            ['key' => 'sidebar_image', 'label' => 'Sidebar image', 'type' => 'image', 'width' => 350, 'height' => 175, 'required' => true],
            ['key' => 'sidebar_headline', 'label' => 'Sidebar headline', 'type' => 'text'],
            ['key' => 'sidebar_body_html', 'label' => 'Sidebar body text', 'type' => 'richtext'],
        ],
        'repeaters' => [['key' => 'bullets', 'label' => 'Bullet point', 'min' => 0, 'max' => 6, 'fields' => [['key' => 'text', 'label' => 'Bullet point', 'type' => 'text']]]],
    ],
    'single_image_specs_detail' => [
        'name' => 'Standard Single Image & Specs Detail', 'description' => 'One image with detailed copy and feature callouts.', 'ai_ready' => true, 'category' => 'Images & text',
        'fields' => [$headline, $image300],
        'repeaters' => [['key' => 'sections', 'label' => 'Detail section', 'min' => 3, 'max' => 4, 'fields' => [$headline, $subheadline, $body]]],
    ],
    'single_left_image' => [
        'name' => 'Standard Single Left Image', 'description' => 'Square image on the left with copy on the right.', 'ai_ready' => true, 'category' => 'Images & text',
        'fields' => [$image300, $headline, array_merge($body, ['required' => true])],
    ],
    'single_right_image' => [
        'name' => 'Standard Single Right Image', 'description' => 'Copy on the left with a square image on the right.', 'ai_ready' => true, 'category' => 'Images & text',
        'fields' => [$headline, array_merge($body, ['required' => true]), $image300],
    ],
    'technical_specifications' => [
        'name' => 'Standard Technical Specifications', 'description' => 'One- or two-column facts and definitions.', 'ai_ready' => false, 'category' => 'Details',
        'fields' => [$headline, ['key' => 'columns', 'label' => 'Layout', 'type' => 'select', 'options' => ['1' => 'One column', '2' => 'Two columns'], 'default' => '1']],
        'repeaters' => [['key' => 'specifications', 'label' => 'Specification', 'min' => 4, 'max' => 16, 'fields' => [
            ['key' => 'specification', 'label' => 'Specification', 'type' => 'text', 'required' => true],
            ['key' => 'definition', 'label' => 'Definition', 'type' => 'text', 'required' => true],
        ]]],
    ],
    'standard_text' => [
        'name' => 'Standard Text', 'description' => 'A clean headline and rich text area.', 'ai_ready' => true, 'category' => 'Text',
        'fields' => [$headline, array_merge($body, ['required' => true])],
    ],
    'three_images_text' => [
        'name' => 'Standard Three Images & Text', 'description' => 'Three equal image and copy columns.', 'ai_ready' => true, 'category' => 'Images & text',
        'fields' => [$headline], 'repeaters' => [['key' => 'items', 'label' => 'Feature', 'min' => 3, 'max' => 3, 'fields' => $standardItem]],
    ],
];
