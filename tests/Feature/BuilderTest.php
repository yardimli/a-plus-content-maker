<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Project;
use App\Models\ProjectModule;
use App\Models\ContentTemplate;
use App\Models\TemplateModule;
use App\Models\User;
use App\Services\ModuleRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class BuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_registry_contains_all_screenshot_modules(): void
    {
        $this->assertCount(17, app(ModuleRegistry::class)->all());
    }

    public function test_every_module_card_has_its_reference_screenshot(): void
    {
        foreach (array_keys(app(ModuleRegistry::class)->all()) as $moduleType) {
            $this->assertFileExists(public_path('images/modules/'.$moduleType.'.png'), $moduleType.' is missing its module-card preview.');
            $this->assertFileExists(public_path('images/modules/rendered/'.$moduleType.'.webp'), $moduleType.' is missing its finished-content preview.');
        }
    }

    public function test_owner_can_add_and_update_a_module(): void
    {
        $user = User::factory()->create();
        $project = Project::create(['uuid' => (string) Str::uuid(), 'user_id' => $user->id, 'name' => 'Test page']);

        $created = $this->actingAs($user)->postJson(route('projects.modules.store', $project), ['module_type' => 'standard_text'])->assertCreated();
        $module = $project->modules()->firstOrFail();
        $content = $module->content;
        $content['headline'] = 'A new world awaits';
        $content['body_html'] = '<p onclick="bad()"><strong>Safe copy</strong><script>alert(1)</script></p>';

        $this->actingAs($user)->patchJson(route('projects.modules.update', [$project, $module]), ['version' => 1, 'content' => $content])->assertOk()->assertJsonPath('data.version', 2);
        $this->assertStringNotContainsString('script', $module->fresh()->content['body_html']);
        $this->assertSame('standard_text', $created->json('data.module_type'));
    }

    public function test_module_can_be_added_with_editable_samples_or_empty(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $project = Project::create(['uuid' => (string) Str::uuid(), 'user_id' => $user->id, 'name' => 'Sample choice']);

        $this->actingAs($user)->postJson(route('projects.modules.store', $project), [
            'module_type' => 'single_left_image', 'populate_samples' => true,
        ])->assertCreated();

        $populated = $project->modules()->where('position', 1)->firstOrFail();
        $this->assertSame('A love that rewrites everything', $populated->content['headline']);
        $this->assertNotNull($populated->content['image']);
        $this->assertDatabaseHas('assets', ['project_id' => $project->id, 'source' => 'sample']);

        $this->actingAs($user)->postJson(route('projects.modules.store', $project), [
            'module_type' => 'single_left_image', 'populate_samples' => false,
        ])->assertCreated();

        $empty = $project->modules()->where('position', 2)->firstOrFail();
        $this->assertNull($empty->content['headline']);
        $this->assertNull($empty->content['image']);
    }

    public function test_project_is_limited_to_five_modules_and_can_add_again_after_removal(): void
    {
        $user = User::factory()->create();
        $project = Project::create(['uuid' => (string) Str::uuid(), 'user_id' => $user->id, 'name' => 'Five module page']);

        foreach (range(1, Project::MAX_MODULES) as $position) {
            ProjectModule::create([
                'uuid' => (string) Str::uuid(),
                'project_id' => $project->id,
                'module_type' => 'standard_text',
                'position' => $position,
                'content' => app(ModuleRegistry::class)->defaults('standard_text'),
                'settings' => [],
            ]);
        }

        $this->actingAs($user)->get(route('projects.builder', $project))
            ->assertOk()
            ->assertSee('Amazon limits A+ Content to 5 modules.')
            ->assertSee('"moduleLimit":5', false);

        $this->actingAs($user)->postJson(route('projects.modules.store', $project), ['module_type' => 'standard_text'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.modules.0', 'Amazon limits A+ Content to 5 modules.');
        $this->assertSame(Project::MAX_MODULES, $project->modules()->count());

        $module = $project->modules()->firstOrFail();
        $this->actingAs($user)->deleteJson(route('projects.modules.destroy', [$project, $module]))->assertOk();
        $this->actingAs($user)->postJson(route('projects.modules.store', $project), ['module_type' => 'standard_text'])->assertCreated();
        $this->assertSame(Project::MAX_MODULES, $project->modules()->count());
    }

    public function test_owner_can_delete_a_project_from_studio_with_its_assets(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $project = Project::create(['uuid' => (string) Str::uuid(), 'user_id' => $owner->id, 'name' => 'Delete me']);
        $path = 'projects/'.$project->uuid.'/image.png';
        Storage::disk('public')->put($path, 'image');
        $asset = Asset::create([
            'user_id' => $owner->id,
            'project_id' => $project->id,
            'disk' => 'public',
            'path' => $path,
            'mime_type' => 'image/png',
        ]);

        $this->actingAs($owner)->get(route('dashboard'))->assertOk()->assertSee('Delete me')->assertSee('Delete');
        $this->actingAs($stranger)->delete(route('projects.destroy', $project))->assertForbidden();
        $this->actingAs($owner)->delete(route('projects.destroy', $project))->assertRedirect(route('projects.index'));

        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
        $this->assertDatabaseMissing('assets', ['id' => $asset->id]);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_another_user_cannot_open_or_change_a_project(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $project = Project::create(['uuid' => (string) Str::uuid(), 'user_id' => $owner->id, 'name' => 'Private page']);

        $this->actingAs($stranger)->get(route('projects.builder', $project))->assertForbidden();
        $this->actingAs($stranger)->postJson(route('projects.modules.store', $project), ['module_type' => 'standard_text'])->assertForbidden();
    }

    public function test_only_admin_can_open_template_studio(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)->get(route('admin.templates.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.templates.index'))->assertOk();
    }

    public function test_template_is_deep_copied_into_a_new_project(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create();
        $template = ContentTemplate::create(['created_by' => $admin->id, 'name' => 'Fiction starter', 'slug' => 'fiction-starter', 'status' => 'published']);
        TemplateModule::create(['template_id' => $template->id, 'module_type' => 'standard_text', 'position' => 1, 'content' => app(ModuleRegistry::class)->defaults('standard_text')]);

        $this->actingAs($user)->post(route('projects.store'), ['name' => 'My page', 'marketplace' => 'amazon.com', 'template_id' => $template->id])->assertRedirect();
        $project = $user->projects()->firstOrFail();
        $this->assertSame($template->id, $project->source_template_id);
        $this->assertSame('standard_text', $project->modules()->firstOrFail()->module_type);
    }

    public function test_seeder_creates_twelve_admin_owned_genre_templates(): void
    {
        $this->seed();

        $this->assertDatabaseCount('templates', 12);
        $this->assertSame(3, ContentTemplate::where('category', 'Romance')->count());
        $this->assertSame(3, ContentTemplate::where('category', 'Science Fiction')->count());
        $this->assertSame(3, ContentTemplate::where('category', 'Fantasy')->count());
        $this->assertSame(3, ContentTemplate::where('category', 'Action & Thriller')->count());
        $this->assertSame(12, ContentTemplate::whereHas('creator', fn ($query) => $query->where('role', 'admin'))->count());
        $this->assertSame(4, ContentTemplate::get()->filter(fn ($template) => in_array('Series', $template->tags ?? []))->count());
    }

    public function test_seeded_page_sequences_match_the_campaign_compositions(): void
    {
        $this->seed();

        $this->assertSame(
            ['single_left_image', 'light_text_overlay', 'three_images_text'],
            ContentTemplate::where('slug', 'letters-at-low-tide')->firstOrFail()->modules()->pluck('module_type')->all()
        );
        $this->assertSame(
            ['comparison_chart', 'dark_text_overlay', 'three_images_text'],
            ContentTemplate::where('slug', 'the-rook-directive')->firstOrFail()->modules()->pluck('module_type')->all()
        );
    }

    public function test_public_template_detail_includes_rendered_modules_and_viewport_controls(): void
    {
        $this->seed();
        $template = ContentTemplate::where('slug', 'letters-at-low-tide')->firstOrFail();

        $this->get(route('templates.show', $template))
            ->assertOk()
            ->assertSee('Rendered template')
            ->assertSee('Desktop')
            ->assertSee('Mobile')
            ->assertSee('template-preview-frame', false)
            ->assertSee('template-preview-data', false)
            ->assertSee('light_text_overlay', false)
            ->assertSee('letters-at-low-tide', false);
    }

    public function test_writing_partner_form_stays_open_for_generation_and_can_apply_over_existing_copy(): void
    {
        $user = User::factory()->create();
        $project = Project::create(['uuid' => (string) Str::uuid(), 'user_id' => $user->id, 'name' => 'Writing partner']);

        $this->actingAs($user)->get(route('projects.builder', $project))
            ->assertOk()
            ->assertSee('id="ai-form"', false)
            ->assertDontSee('method="dialog" class="dialog-shell" id="ai-form"', false)
            ->assertSee('id="ai-generate"', false);

        $javascript = file_get_contents(resource_path('js/builder.js'));
        $this->assertStringContainsString('Apply draft', $javascript);
        $this->assertStringNotContainsString('Apply to empty fields', $javascript);
    }

    public function test_series_template_clones_distinct_visual_assets_and_multi_book_slots(): void
    {
        Storage::fake('public');
        $this->seed();
        $user = User::factory()->create();
        $template = ContentTemplate::whereJsonContains('tags', 'Series')->with('modules')->firstOrFail();

        $this->actingAs($user)->post(route('projects.store'), ['name' => 'My series', 'marketplace' => 'amazon.com', 'template_id' => $template->id])->assertRedirect();

        $project = $user->projects()->with(['modules', 'assets'])->firstOrFail();
        $comparison = $project->modules->firstWhere('module_type', 'comparison_chart');
        $this->assertCount(3, $comparison->content['products']);
        $this->assertNotNull($comparison->content['products'][0]['image']);
        $this->assertCount(3, array_unique(array_column($comparison->content['products'], 'image')));
        $this->assertCount(7, $project->assets);
        foreach ($project->assets as $asset) {
            Storage::disk('public')->assertExists($asset->path);
        }
    }

    public function test_single_book_template_uses_a_distinct_image_for_each_block_slot(): void
    {
        $this->seed();
        $template = ContentTemplate::where('slug', 'letters-at-low-tide')->with('modules')->firstOrFail();
        $paths = [];
        $content = $template->modules->pluck('content')->all();
        array_walk_recursive($content, function ($value) use (&$paths): void {
            if (is_string($value) && str_starts_with($value, '/images/templates/blocks/')) {
                $paths[] = $value;
            }
        });

        $this->assertCount(5, $paths);
        $this->assertCount(5, array_unique($paths));
        foreach ($paths as $path) {
            $this->assertFileExists(public_path(ltrim($path, '/')));
        }
    }

    public function test_all_gallery_templates_use_exact_fit_artwork_and_white_book_mockups(): void
    {
        $templates = [
            'letters-at-low-tide' => false,
            'the-cinnamon-bookshop' => false,
            'hearts-of-hawthorne-bay' => true,
            'orbit-of-ash' => false,
            'the-memory-cartographer' => false,
            'the-meridian-expanse' => true,
            'a-crown-of-briars' => false,
            'the-mapmakers-dragon' => false,
            'chronicles-of-emberfall' => true,
            'the-black-harbor' => false,
            'zero-hour-witness' => false,
            'the-rook-directive' => true,
        ];

        $assertDimensions = function (string $path, int $width, int $height): void {
            $this->assertFileExists($path);
            $dimensions = getimagesize($path);
            $this->assertIsArray($dimensions, 'Unable to read image dimensions for '.$path);
            $this->assertSame([$width, $height], array_slice($dimensions, 0, 2), $path.' does not match its module target.');
        };

        $assertWhiteCorners = function (string $path): void {
            $image = imagecreatefromwebp($path);
            $this->assertInstanceOf(\GdImage::class, $image);
            $points = [[0, 0], [imagesx($image) - 1, 0], [0, imagesy($image) - 1], [imagesx($image) - 1, imagesy($image) - 1]];

            foreach ($points as [$x, $y]) {
                $rgb = imagecolorsforindex($image, imagecolorat($image, $x, $y));
                $this->assertGreaterThanOrEqual(245, min($rgb['red'], $rgb['green'], $rgb['blue']), $path.' must use a white book-mockup background.');
            }

            imagedestroy($image);
        };

        foreach ($templates as $slug => $isSeries) {
            $root = public_path('images/templates/blocks/'.$slug);
            $sourceRoot = public_path('images/templates/generated-sources/'.$slug);
            $assertDimensions($root.'/hero.webp', 970, 300);
            $assertDimensions($root.'/cover.webp', 300, 300);
            $assertWhiteCorners($root.'/cover.webp');

            foreach ([1, 2, 3] as $index) {
                $this->assertFileExists($sourceRoot.'/feature-'.$index.'.png');
                $assertDimensions($root.'/feature-'.$index.'.webp', 300, 300);
            }

            $this->assertFileExists($sourceRoot.'/hero.png');

            if ($isSeries) {
                foreach ([1, 2, 3] as $index) {
                    $bookPath = $root.'/book-'.$index.'.webp';
                    $assertDimensions($bookPath, 200, 300);
                    $assertWhiteCorners($bookPath);
                }
            }
        }
    }

    public function test_asin_lookup_is_mapped_and_never_exposes_provider_headers(): void
    {
        config(['services.amazon_product_data.key' => 'test-secret', 'services.amazon_product_data.base_url' => 'https://products.test']);
        Http::fake(['products.test/product/*' => Http::response(['success' => true, 'data' => ['asin' => 'B099LJNCHQ', 'title' => 'Backyard Starship', 'rating' => 4.5, 'review_count' => 100, 'features' => [], 'image_url' => 'https://example.test/cover.jpg', 'product_url' => 'https://amazon.com/dp/B099LJNCHQ']], 200)]);
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('asin.lookup'), ['asin' => 'B099LJNCHQ'])->assertOk()->assertJsonPath('data.title', 'Backyard Starship')->assertJsonMissing(['test-secret']);
        $this->assertDatabaseHas('asin_lookups', ['asin' => 'B099LJNCHQ', 'was_successful' => true]);
    }

    public function test_series_book_slot_can_import_an_asin_cover(): void
    {
        Storage::fake('public');
        config(['services.amazon_product_data.key' => 'test-secret', 'services.amazon_product_data.base_url' => 'https://products.test']);
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
        Http::fake([
            'products.test/product/*' => Http::response(['success' => true, 'data' => ['asin' => 'B099LJNCHQ', 'title' => 'Backyard Starship', 'image_url' => 'https://m.media-amazon.com/images/I/test.png', 'product_url' => 'https://amazon.com/dp/B099LJNCHQ']], 200),
            'm.media-amazon.com/*' => Http::response($png, 200, ['Content-Type' => 'image/png']),
        ]);
        $user = User::factory()->create();
        $project = Project::create(['uuid' => (string) Str::uuid(), 'user_id' => $user->id, 'name' => 'Series page']);

        $this->actingAs($user)->postJson(route('projects.asin.import', $project), ['asin' => 'B099LJNCHQ'])
            ->assertCreated()->assertJsonPath('data.product.title', 'Backyard Starship')->assertJsonPath('data.asset.source', 'asin');

        $this->assertDatabaseHas('assets', ['project_id' => $project->id, 'source' => 'asin', 'alt_text' => 'Backyard Starship cover']);
    }

    public function test_asset_details_can_be_updated_only_by_the_owner(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $project = Project::create(['uuid' => (string) Str::uuid(), 'user_id' => $owner->id, 'name' => 'Asset library']);
        $asset = Asset::create([
            'user_id' => $owner->id, 'project_id' => $project->id, 'disk' => 'public', 'path' => 'projects/example/image.png',
            'original_name' => 'image.png', 'mime_type' => 'image/png', 'width' => 300, 'height' => 150, 'alt_text' => 'Old description',
        ]);

        $this->actingAs($stranger)->patchJson(route('assets.update', $asset), ['alt_text' => 'Not allowed'])->assertForbidden();
        $this->actingAs($owner)->patchJson(route('assets.update', $asset), ['alt_text' => 'A blue abstract book banner'])->assertOk()
            ->assertJsonPath('data.alt_text', 'A blue abstract book banner');
        $this->assertDatabaseHas('assets', ['id' => $asset->id, 'alt_text' => 'A blue abstract book banner']);
    }

    public function test_uploaded_asset_returns_dimensions_for_exact_fit_workflow(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $project = Project::create(['uuid' => (string) Str::uuid(), 'user_id' => $user->id, 'name' => 'Crop project']);

        $this->actingAs($user)->post(route('projects.assets.store', $project), [
            'image' => UploadedFile::fake()->image('cropped.png', 600, 180),
            'alt_text' => 'A panoramic fantasy landscape',
        ])->assertCreated()->assertJsonPath('data.width', 600)->assertJsonPath('data.height', 180);

        $this->assertDatabaseHas('assets', ['project_id' => $project->id, 'width' => 600, 'height' => 180]);
    }
}
