<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ContentTemplate;
use App\Models\TemplateModule;
use App\Models\User;
use App\Services\ModuleRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
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

    public function test_series_template_clones_visual_asset_and_multi_book_slots(): void
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
        $this->assertSame($comparison->content['products'][0]['image'], $comparison->content['products'][2]['image']);
        $this->assertCount(1, $project->assets);
        Storage::disk('public')->assertExists($project->assets->first()->path);
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
}
