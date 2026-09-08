<?php

namespace Tests\Feature;

use App\Models\{Asset, ContentTemplate, Project, ProjectModule, TemplateModule, User};
use App\Services\ImageFilters;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ImageFiltersTest extends TestCase
{
    use RefreshDatabase;

    private function project(User $user, array $stack = []): Project
    {
        return Project::create(['uuid' => (string) Str::uuid(), 'user_id' => $user->id, 'name' => 'Filters', 'image_filters' => $stack]);
    }

    private function png(): string
    {
        $image = imagecreatetruecolor(2, 1);
        imagealphablending($image, false); imagesavealpha($image, true);
        imagesetpixel($image, 0, 0, imagecolorallocatealpha($image, 100, 60, 20, 40));
        ob_start(); imagepng($image); $bytes = ob_get_clean(); imagedestroy($image);
        return $bytes;
    }

    public function test_order_changes_pixels_and_preserves_alpha_and_dimensions(): void
    {
        $filters = app(ImageFilters::class);
        $a = ['id' => 'normal', 'values' => ['brightness' => 2]];
        $b = ['id' => 'normal', 'values' => ['contrast' => 0]];
        $first = imagecreatefromstring($filters->render($this->png(), [$a, $b]));
        $second = imagecreatefromstring($filters->render($this->png(), [$b, $a]));
        $this->assertSame(128, imagecolorat($first, 0, 0) & 255);
        $this->assertSame(255, imagecolorat($second, 0, 0) & 255);
        $this->assertSame(40, (imagecolorat($first, 0, 0) >> 24) & 127);
        $this->assertSame(2, imagesx($first));
        imagedestroy($first); imagedestroy($second);
    }

    public function test_stack_validation_removal_and_authorization(): void
    {
        $user = User::factory()->create(); $project = $this->project($user);
        $url = route('projects.update', $project);
        $this->actingAs($user)->patchJson($url, ['image_filters' => array_fill(0, 4, ['id' => 'aden'])])->assertUnprocessable();
        $this->patchJson($url, ['image_filters' => ['named' => ['id' => 'aden']]])->assertUnprocessable();
        $this->patchJson($url, ['image_filters' => [['id' => 'fake']]])->assertUnprocessable();
        $this->patchJson($url, ['image_filters' => [['id' => 'aden', 'values' => ['brightness' => 99]]]])->assertUnprocessable();
        $this->patchJson($url, ['image_filters' => [['id' => 'aden', 'values' => ['unexpected' => 1]]]])->assertUnprocessable();
        $this->patchJson($url, ['image_filters' => [['id' => 'aden']]])->assertOk();
        $this->assertSame([['id' => 'aden']], $project->fresh()->image_filters);
        $this->patchJson($url, ['image_filters' => []])->assertOk();
        $this->assertSame([], $project->fresh()->image_filters);
        $this->actingAs(User::factory()->create())->patchJson($url, ['image_filters' => []])->assertForbidden();
    }

    public function test_admin_defaults_and_slot_opt_out_are_cloned_and_editable(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $stack = [['id' => 'aden', 'values' => ['contrast' => 1.2]]];
        $this->actingAs($admin)->postJson(route('admin.templates.store'), ['name' => 'Filter template', 'status' => 'published', 'image_filters' => $stack])->assertCreated();
        $template = ContentTemplate::firstOrFail();
        $module = TemplateModule::create(['template_id' => $template->id, 'module_type' => 'company_logo', 'position' => 1, 'content' => []]);
        $this->patchJson(route('admin.templates.modules.update', [$template, $module]), ['content' => ['image' => null, 'image_apply_filters' => false]])->assertOk();
        $this->assertFalse($module->fresh()->content['image_apply_filters']);
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('projects.store'), ['name' => 'Copy', 'template_id' => $template->id, 'marketplace' => 'amazon.com'])->assertRedirect();
        $project = Project::firstOrFail();
        $this->assertSame($stack, $project->image_filters);
        $module = $project->modules()->firstOrFail();
        $this->assertFalse($module->content['image_apply_filters']);
        $this->patchJson(route('projects.modules.update', [$project, $module]), ['version' => $module->version, 'content' => ['image' => null, 'image_apply_filters' => true]])->assertOk();
        $this->assertTrue($module->fresh()->content['image_apply_filters']);
    }

    public function test_transfer_preview_matches_download_and_respects_each_occurrence(): void
    {
        Storage::fake('public'); Storage::fake('local');
        $user = User::factory()->create();
        $project = $this->project($user, [['id' => 'normal', 'values' => ['brightness' => 0]]]);
        $bytes = $this->png(); Storage::disk('public')->put('sample.png', $bytes);
        $asset = Asset::create(['user_id' => $user->id, 'project_id' => $project->id, 'disk' => 'public', 'path' => 'sample.png', 'original_name' => 'sample.png', 'mime_type' => 'image/png', 'width' => 2, 'height' => 1]);
        $module = ProjectModule::create(['project_id' => $project->id, 'uuid' => (string) Str::uuid(), 'module_type' => 'three_images_text', 'position' => 1, 'content' => ['items' => [['image' => $asset->id], ['image' => $asset->id, 'image_apply_filters' => false]]]]);
        $url = route('projects.transfer.download', [$project, $asset, 'module' => $module->uuid, 'slot' => 'items.0.image']);
        $download = $this->actingAs($user)->get($url)->assertOk()->assertDownload('sample-'.$asset->id.'-filtered.png');
        $preview = $this->get($url.'&preview=1')->assertOk();
        $this->assertSame($download->streamedContent(), $preview->getContent());
        $this->assertNotSame($bytes, $preview->getContent());
        $skip = route('projects.transfer.download', [$project, $asset, 'module' => $module->uuid, 'slot' => 'items.1.image', 'preview' => 1]);
        $this->assertSame($bytes, $this->get($skip)->assertOk()->getContent());
        $this->get($url.'junk')->assertNotFound();
        $this->get(route('projects.transfer', $project))->assertOk()->assertSee('items.0.image')->assertSee('items.1.image');
        $this->actingAs(User::factory()->create())->get($url.'&preview=1')->assertForbidden();
    }
}
