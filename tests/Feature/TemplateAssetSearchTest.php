<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class TemplateAssetSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config(['services.serper.key' => 'serper-test-key', 'services.serper.base_url' => 'https://serper.test']);
    }

    public function test_search_uses_provider_suffix_filters_results_and_caches_the_api_response(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create();
        Http::fake(['serper.test/images' => Http::response(['images' => [
            $this->imageResult('https://images.pexels.com/photos/apple.jpg', 1),
            $this->imageResult('https://cdn.example.com/not-allowed.jpg', 2),
            $this->imageResult('https://images.unsplash.com/photo-apple.jpg', 3),
        ]])]);

        $url = route('assets.search', ['query' => 'red apple', 'source' => 'pexels']);
        $this->actingAs($user)->getJson($url)->assertOk()
            ->assertJsonPath('data.query', 'red apple pexels')
            ->assertJsonCount(2, 'data.images')
            ->assertJsonStructure(['data' => ['images' => [['title', 'image_url', 'thumbnail_url', 'token']]]]);
        $this->actingAs($admin)->getJson($url)->assertOk()
            ->assertJsonPath('data.query', 'red apple pexels')
            ->assertJsonCount(2, 'data.images');

        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->url() === 'https://serper.test/images'
            && $request->hasHeader('X-API-KEY', 'serper-test-key')
            && $request['q'] === 'red apple pexels'
            && $request['num'] === 100);
    }

    public function test_admin_can_import_both_full_image_and_thumbnail_from_a_signed_result(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
        Http::fake([
            'serper.test/images' => Http::response(['images' => [$this->imageResult('https://images.pexels.com/photos/apple.png', 1)]]),
            'images.pexels.com/*' => Http::response($png, 200, ['Content-Type' => 'image/png']),
            'encrypted-tbn0.gstatic.com/*' => Http::response($png, 200, ['Content-Type' => 'image/png']),
        ]);

        $token = $this->actingAs($admin)->getJson(route('assets.search', ['query' => 'apple', 'source' => 'pexels']))
            ->assertOk()->json('data.images.0.token');

        $response = $this->postJson(route('admin.template-assets.import'), ['token' => $token])
            ->assertCreated()
            ->assertJsonPath('data.alt_text', 'Apple on a table')
            ->assertJsonPath('data.source', 'template');

        $asset = Asset::findOrFail($response->json('data.id'));
        Storage::disk('public')->assertExists($asset->path);
        Storage::disk('public')->assertExists($asset->metadata['thumbnail_path']);
        $this->assertStringContainsString('/storage/template-assets/', $response->json('data.thumbnail_url'));
        $this->assertSame('https://images.pexels.com/photos/apple.png', $asset->metadata['remote_image_url']);
    }

    public function test_non_admin_cannot_import_into_the_template_library(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson(route('admin.template-assets.import'), ['token' => 'invalid'])->assertForbidden();
    }

    public function test_user_can_import_a_search_result_into_their_own_project(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $project = Project::create(['uuid' => (string) Str::uuid(), 'user_id' => $user->id, 'name' => 'Stock image project']);
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
        Http::fake([
            'serper.test/images' => Http::response(['images' => [$this->imageResult('https://images.pexels.com/photos/apple.png', 1)]]),
            'images.pexels.com/*' => Http::response($png, 200, ['Content-Type' => 'image/png']),
            'encrypted-tbn0.gstatic.com/*' => Http::response($png, 200, ['Content-Type' => 'image/png']),
        ]);

        $token = $this->actingAs($user)->getJson(route('assets.search', ['query' => 'apple', 'source' => 'pexels']))
            ->assertOk()->json('data.images.0.token');

        $response = $this->postJson(route('projects.assets.import', $project), ['token' => $token])
            ->assertCreated()->assertJsonPath('data.project_id', $project->id)->assertJsonPath('data.source', 'serper');

        $asset = Asset::findOrFail($response->json('data.id'));
        Storage::disk('public')->assertExists($asset->path);
        Storage::disk('public')->assertExists($asset->metadata['thumbnail_path']);

        $otherUser = User::factory()->create();
        $this->actingAs($otherUser)->postJson(route('projects.assets.import', $project), ['token' => $token])->assertForbidden();
    }

    public function test_template_editor_renders_the_standalone_stock_search_controls(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $template = \App\Models\ContentTemplate::create(['created_by' => $admin->id, 'name' => 'Search template', 'slug' => 'search-template', 'status' => 'draft']);

        $this->actingAs($admin)->get(route('admin.templates.edit', $template))->assertOk()
            ->assertSee('Search stock images')
            ->assertSee('id="asset-stock-search-dialog"', false)
            ->assertSee('<option value="pexels" selected>Pexels</option>', false)
            ->assertSee('<option value="100"', false);

        $this->assertStringContainsString('grid-template-columns:repeat(5,minmax(0,1fr))', file_get_contents(resource_path('css/app.css')));
        $this->assertStringContainsString('asset.thumbnail_url || asset.url', file_get_contents(resource_path('js/builder.js')));
    }

    public function test_project_builder_renders_stock_search_for_regular_users(): void
    {
        $user = User::factory()->create();
        $project = Project::create(['uuid' => (string) Str::uuid(), 'user_id' => $user->id, 'name' => 'User builder']);

        $this->actingAs($user)->get(route('projects.builder', $project))->assertOk()
            ->assertSee('id="asset-stock-search-open"', false)
            ->assertSee('id="asset-stock-search-dialog"', false)
            ->assertSee('"assetSearch":', false)
            ->assertSee('"assetImport":', false);
    }

    private function imageResult(string $imageUrl, int $position): array
    {
        return [
            'title' => 'Apple on a table',
            'imageUrl' => $imageUrl,
            'imageWidth' => 1200,
            'imageHeight' => 800,
            'thumbnailUrl' => 'https://encrypted-tbn0.gstatic.com/images?q=apple-'.$position,
            'source' => 'Pexels',
            'domain' => 'www.pexels.com',
            'link' => 'https://www.pexels.com/photo/apple-'.$position,
            'position' => $position,
        ];
    }
}
