<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Project;
use App\Models\ProjectModule;
use App\Models\User;
use App\Services\ModuleRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class TransferGuideTest extends TestCase
{
    use RefreshDatabase;

    private function project(User $user): Project
    {
        return Project::create(['uuid' => (string) Str::uuid(), 'user_id' => $user->id, 'name' => 'My transfer', 'asin' => 'B099LJNCHQ', 'marketplace' => 'amazon.com']);
    }

    public function test_guide_handles_every_registered_module_and_incomplete_content(): void
    {
        $user = User::factory()->create();
        $project = $this->project($user);
        foreach (app(ModuleRegistry::class)->all() as $type => $definition) {
            $project->modules()->delete();
            ProjectModule::create(['uuid' => (string) Str::uuid(), 'project_id' => $project->id, 'module_type' => $type, 'position' => 1, 'content' => app(ModuleRegistry::class)->defaults($type)]);
            $this->actingAs($user)->get(route('projects.transfer', $project))->assertOk()
                ->assertSee($definition['name'])->assertSee('Copy your design to KDP')->assertSee('B099LJNCHQ');
        }
        $this->get(route('projects.builder', $project))->assertOk()->assertSee('Copy to KDP')->assertDontSee('Export for KDP');
    }

    public function test_guide_orders_modules_sanitizes_rich_text_and_splits_comparison_values(): void
    {
        $user = User::factory()->create();
        $project = $this->project($user);
        ProjectModule::create(['uuid' => (string) Str::uuid(), 'project_id' => $project->id, 'module_type' => 'standard_text', 'position' => 2, 'content' => ['headline' => 'Second module', 'body_html' => '<p onclick="bad()"><strong>Rich copy</strong></p>']]);
        ProjectModule::create(['uuid' => (string) Str::uuid(), 'project_id' => $project->id, 'module_type' => 'comparison_chart', 'position' => 1, 'content' => ['show_reviews' => true, 'show_prices' => false, 'products' => [['title' => 'First book'], ['title' => 'Second book']], 'metrics' => [['label' => 'Reading order', 'values' => 'Volume one|Volume two']]]]);
        $this->actingAs($user)->get(route('projects.transfer', $project))->assertOk()
            ->assertSeeInOrder(['Standard Comparison Chart', 'Standard Text'])
            ->assertSee('Volume one')->assertSee('Volume two')->assertDontSee('Volume one|Volume two')
            ->assertSee('<strong>Rich copy</strong>', false)->assertDontSee('onclick="bad()"', false);
    }

    public function test_guide_and_image_downloads_are_authorized_and_webp_downloads_as_png(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $project = $this->project($user);
        $image = imagecreatetruecolor(30, 20);
        ob_start(); imagewebp($image); $bytes = ob_get_clean(); imagedestroy($image);
        Storage::disk('public')->put('test.webp', $bytes);
        $asset = Asset::create(['project_id' => $project->id, 'user_id' => $user->id, 'disk' => 'public', 'path' => 'test.webp', 'original_name' => 'Book cover.webp', 'mime_type' => 'image/webp', 'width' => 30, 'height' => 20]);
        $this->actingAs(User::factory()->create())->get(route('projects.transfer', $project))->assertForbidden();
        $this->get(route('projects.transfer.download', [$project, $asset]))->assertForbidden();
        $otherProject = $this->project($user);
        $this->actingAs($user)->get(route('projects.transfer.download', [$otherProject, $asset]))->assertNotFound();
        $download = $this->get(route('projects.transfer.download', [$project, $asset]))->assertOk()->assertDownload('book-cover-'.$asset->id.'.png');
        $this->assertSame([30, 20], array_slice(getimagesizefromstring($download->streamedContent()), 0, 2));
        Storage::disk('public')->delete('test.webp');
        $this->get(route('projects.transfer.download', [$project, $asset]))->assertNotFound();
    }
}
