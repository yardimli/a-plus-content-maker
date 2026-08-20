<?php

namespace Database\Seeders;

use App\Models\ContentTemplate;
use App\Models\TemplateModule;
use App\Models\User;
use App\Services\ModuleRegistry;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $password = (string) config('services.admin.password');
        if ($password === '') {
            $password = app()->environment('production') ? throw new \RuntimeException('ADMIN_PASSWORD is required in production.') : 'APlusAdmin!2026';
        }
        $admin = User::updateOrCreate(
            ['email' => (string) config('services.admin.email')],
            ['name' => config('services.admin.name'), 'password' => Hash::make($password), 'role' => 'admin', 'is_active' => true, 'email_verified_at' => now()]
        );

        ContentTemplate::whereIn('slug', ['storyworld-spotlight', 'nonfiction-authority', 'series-reading-path'])->delete();

        $templates = [
            ['genre' => 'Romance', 'name' => 'Letters at Low Tide', 'slug' => 'letters-at-low-tide', 'author' => 'Mara Ellis', 'image' => 'romance-letters-low-tide.png', 'tagline' => 'A second chance. A hidden past. A love that rewrites everything.', 'summary' => 'A windswept coastal romance layout built around memory, place, and emotional promise.'],
            ['genre' => 'Romance', 'name' => 'The Cinnamon Bookshop', 'slug' => 'the-cinnamon-bookshop', 'author' => 'June Harlow', 'image' => 'romance-cinnamon-bookshop.png', 'tagline' => 'Love is always in season.', 'summary' => 'A warm small-town romance campaign with bookshop charm and cozy editorial details.'],
            ['genre' => 'Romance', 'name' => 'Hearts of Hawthorne Bay', 'slug' => 'hearts-of-hawthorne-bay', 'author' => 'Clara Vale', 'image' => 'romance-hawthorne-bay-series.png', 'tagline' => 'Three stories. One timeless place.', 'summary' => 'A coordinated coastal romance trilogy with replaceable ASIN cover slots.', 'books' => ['The Summer We Returned', 'The Promise We Kept', 'The Harbor We Chose']],
            ['genre' => 'Science Fiction', 'name' => 'Orbit of Ash', 'slug' => 'orbit-of-ash', 'author' => 'Elias Voss', 'image' => 'scifi-orbit-of-ash.png', 'tagline' => 'A dead world. A broken ring. One chance to rewrite humanity’s future.', 'summary' => 'A cinematic hard-science-fiction layout for vast worlds and high-concept stakes.'],
            ['genre' => 'Science Fiction', 'name' => 'The Memory Cartographer', 'slug' => 'the-memory-cartographer', 'author' => 'Sera Quill', 'image' => 'scifi-memory-cartographer.png', 'tagline' => 'She maps memories. He erases them.', 'summary' => 'A cerebral neon-noir science-fiction campaign about identity, truth, and remembrance.'],
            ['genre' => 'Science Fiction', 'name' => 'The Meridian Expanse', 'slug' => 'the-meridian-expanse', 'author' => 'Iona Reeve', 'image' => 'scifi-meridian-expanse-series.png', 'tagline' => 'Humanity is not alone.', 'summary' => 'A premium space-opera trilogy layout with replaceable ASIN cover slots.', 'books' => ['Starfall Protocol', 'The Silent Colony', 'Beyond the Eventide']],
            ['genre' => 'Fantasy', 'name' => 'A Crown of Briars', 'slug' => 'a-crown-of-briars', 'author' => 'Lyra Thorne', 'image' => 'fantasy-crown-of-briars.png', 'tagline' => 'Love is a thorn. The throne is its price.', 'summary' => 'A luxurious dark romantasy campaign of fae courts, forbidden love, and dangerous power.'],
            ['genre' => 'Fantasy', 'name' => 'The Mapmaker’s Dragon', 'slug' => 'the-mapmakers-dragon', 'author' => 'Tobias Wren', 'image' => 'fantasy-mapmakers-dragon.png', 'tagline' => 'Some maps show the way. Others wake an adventure.', 'summary' => 'A cozy adventure-fantasy layout filled with maps, magic, and unlikely friendship.'],
            ['genre' => 'Fantasy', 'name' => 'Chronicles of Emberfall', 'slug' => 'chronicles-of-emberfall', 'author' => 'Rowan Drake', 'image' => 'fantasy-emberfall-series.png', 'tagline' => 'Three realms. An ancient prophecy. One final choice.', 'summary' => 'An epic fantasy trilogy campaign with replaceable ASIN cover slots.', 'books' => ['The Ashen Gate', 'The Glass Citadel', 'The Last Fireborn']],
            ['genre' => 'Action & Thriller', 'name' => 'The Black Harbor', 'slug' => 'the-black-harbor', 'author' => 'Daniel Cross', 'image' => 'thriller-black-harbor.png', 'tagline' => 'A deadly shipment. A corrupt port. A race against betrayal.', 'summary' => 'A rain-soaked maritime thriller layout for conspiracies, pursuit, and explosive secrets.'],
            ['genre' => 'Action & Thriller', 'name' => 'Zero Hour Witness', 'slug' => 'zero-hour-witness', 'author' => 'Nora Kane', 'image' => 'thriller-zero-hour-witness.png', 'tagline' => 'One witness. Forty-eight hours. Nowhere to hide.', 'summary' => 'A high-velocity urban thriller campaign driven by a relentless countdown.'],
            ['genre' => 'Action & Thriller', 'name' => 'The Rook Directive', 'slug' => 'the-rook-directive', 'author' => 'Jack Mercer', 'image' => 'thriller-rook-directive-series.png', 'tagline' => 'Three missions. One shadow war.', 'summary' => 'An international action-thriller trilogy with replaceable ASIN cover slots.', 'books' => ['Dead Drop', 'Red Meridian', 'Final Extraction']],
        ];

        $registry = app(ModuleRegistry::class);
        foreach ($templates as $index => $data) {
            $image = '/images/templates/'.$data['image'];
            $isSeries = isset($data['books']);
            $template = ContentTemplate::updateOrCreate(
                ['slug' => $data['slug']],
                [
                    'created_by' => $admin->id, 'name' => $data['name'], 'summary' => $data['summary'],
                    'description' => $data['summary'].' Clone the complete sequence, replace its artwork and copy, then export an organized KDP transfer package.',
                    'category' => $data['genre'], 'tags' => [$data['genre'], $isSeries ? 'Series' : 'Single Book', 'Authors', 'AI Ready'],
                    'preview_image' => $image, 'status' => 'published', 'is_featured' => $index % 3 === 0, 'published_at' => now(),
                ]
            );
            $template->modules()->delete();

            if ($isSeries) {
                $comparison = $registry->defaults('comparison_chart');
                $comparison['show_reviews'] = true;
                $comparison['show_prices'] = true;
                $comparison['show_add_to_cart'] = false;
                $comparison['products'] = collect($data['books'])->map(fn ($title, $bookIndex) => ['asin' => null, 'image' => $image, 'title' => $title, 'highlighted' => $bookIndex === 0])->all();
                $comparison['metrics'] = [
                    ['label' => 'Reading order', 'values' => 'Book 1|Book 2|Book 3'],
                    ['label' => 'Series world', 'values' => $data['name'].'|'.$data['name'].'|'.$data['name']],
                    ['label' => 'Format', 'values' => 'Replace via ASIN|Replace via ASIN|Replace via ASIN'],
                ];
                TemplateModule::create(['template_id' => $template->id, 'module_type' => 'comparison_chart', 'position' => 1, 'content' => $comparison, 'settings' => ['multi_asin' => true, 'matches_preview' => 'book-lineup']]);
            } else {
                $feature = $registry->defaults('single_left_image');
                $feature['image'] = $image;
                $feature['headline'] = $data['tagline'];
                $feature['body_html'] = '<p>'.$data['summary'].' Replace the campaign artwork with your own cover-led composition.</p>';
                TemplateModule::create(['template_id' => $template->id, 'module_type' => 'single_left_image', 'position' => 1, 'content' => $feature, 'settings' => ['matches_preview' => 'cover-and-copy']]);
            }

            $overlayType = $data['genre'] === 'Romance' ? 'light_text_overlay' : 'dark_text_overlay';
            $overlay = $registry->defaults($overlayType);
            $overlay['background'] = $image;
            $overlay['headline'] = $data['tagline'];
            $overlay['body_html'] = '<p>Use this panoramic section to establish the setting, stakes, and emotional atmosphere shown in the campaign preview.</p>';
            TemplateModule::create(['template_id' => $template->id, 'module_type' => $overlayType, 'position' => 2, 'content' => $overlay, 'settings' => ['matches_preview' => 'panoramic-story-panel']]);

            $panels = $registry->defaults('three_images_text');
            $panels['headline'] = $isSeries ? 'Continue the journey across every book' : 'What readers will discover';
            $panelTitles = $isSeries ? $data['books'] : ['The atmosphere', 'The story promise', 'The reader experience'];
            $panels['items'] = collect($panelTitles)->take(3)->map(fn ($title, $panelIndex) => [
                'image' => $image,
                'headline' => $title,
                'body_html' => $isSeries
                    ? '<p>Book '.($panelIndex + 1).' in '.$data['name'].'. Replace this panel with title-specific artwork and spoiler-free copy.</p>'
                    : '<p>Replace this panel with a focused visual and concise copy drawn from your book’s themes.</p>',
            ])->all();
            TemplateModule::create(['template_id' => $template->id, 'module_type' => 'three_images_text', 'position' => 3, 'content' => $panels, 'settings' => ['matches_preview' => 'editorial-feature-panels']]);
        }
    }
}
