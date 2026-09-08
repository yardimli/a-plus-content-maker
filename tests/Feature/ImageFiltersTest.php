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

    public function test_single_filter_invert_preserves_alpha_and_dimensions(): void
    {
        $filters = app(ImageFilters::class);
        $a = ['id' => 'normal', 'values' => ['invert' => 1]];
        $b = ['id' => 'normal', 'values' => ['brightness' => 0]];
        $first = imagecreatefromstring($filters->render($this->png(), [$a, $b]));
        $pixel = imagecolorat($first, 0, 0);
        $this->assertSame(155, ($pixel >> 16) & 255);
        $this->assertSame(235, $pixel & 255);
        $this->assertSame(40, ($pixel >> 24) & 127);
        $this->assertSame(2, imagesx($first));
        imagedestroy($first);
    }

    public function test_stack_validation_removal_and_authorization(): void
    {
        $user = User::factory()->create(); $project = $this->project($user);
        $url = route('projects.update', $project);
        $this->actingAs($user)->patchJson($url, ['image_filters' => array_fill(0, 2, ['id' => 'aden'])])->assertUnprocessable();
        $this->patchJson($url, ['image_filters' => ['named' => ['id' => 'aden']]])->assertUnprocessable();
        $this->patchJson($url, ['image_filters' => [['id' => 'fake']]])->assertUnprocessable();
        $this->patchJson($url, ['image_filters' => [['id' => 'aden', 'values' => ['brightness' => 99]]]])->assertUnprocessable();
        $this->patchJson($url, ['image_filters' => [['id' => 'aden', 'values' => ['unexpected' => 1]]]])->assertUnprocessable();
        foreach ([['type' => 'fake'], ['color1' => 'url(bad)'], ['opacity' => 2], ['stop1' => -1], ['blend' => 'fake'], ['unexpected' => 1]] as $overlay) {
            $this->patchJson($url, ['image_filters' => [['id' => 'normal', 'overlay' => $overlay]]])->assertUnprocessable();
        }
        $this->patchJson($url, ['image_filters' => [['id' => 'aden']]])->assertOk();
        $this->assertSame([['id' => 'aden']], $project->fresh()->image_filters);
        $this->patchJson($url, ['image_filters' => []])->assertOk();
        $this->assertSame([], $project->fresh()->image_filters);
        $this->actingAs(User::factory()->create())->patchJson($url, ['image_filters' => []])->assertForbidden();
    }

    public function test_overlay_blends_before_filters_and_gradient_direction_is_respected(): void
    {
        $filters = app(ImageFilters::class);
        $filter = ['id' => 'normal', 'values' => ['invert' => 1], 'overlay' => ['type' => 'solid', 'color1' => '#ff0000', 'blend' => 'normal']];
        $image = imagecreatefromstring($filters->render($this->png(), [$filter]));
        $this->assertSame(0x00ffff, imagecolorat($image, 0, 0)); // red overlay, then invert
        imagedestroy($image);
        $filter = ['id' => 'normal', 'overlay' => ['type' => 'linear', 'color1' => '#000000', 'color2' => '#ffffff', 'direction' => 'to right']];
        $image = imagecreatefromstring($filters->render($this->png(), [$filter]));
        $this->assertSame(64, imagecolorat($image, 0, 0) & 255);
        $this->assertSame(191, imagecolorat($image, 1, 0) & 255);
        imagedestroy($image);
        $filter['overlay']['direction'] = 'to left';
        $image = imagecreatefromstring($filters->render($this->png(), [$filter]));
        $this->assertSame(191, imagecolorat($image, 0, 0) & 255);
        imagedestroy($image);
    }

    public function test_overlay_zero_opacity_is_identity_and_blur_spreads_pixels(): void
    {
        $filters = app(ImageFilters::class);
        $base = $filters->render($this->png(), [['id' => 'normal']]);
        $this->assertSame($base, $filters->render($this->png(), [['id' => 'normal', 'overlay' => ['type' => 'solid', 'opacity' => 0]]]));
        $image = imagecreatetruecolor(15, 15);
        imagefill($image, 0, 0, 0); imagesetpixel($image, 7, 7, 0xffffff);
        ob_start(); imagepng($image); $bytes = ob_get_clean(); imagedestroy($image);
        $blurred = imagecreatefromstring($filters->render($bytes, [['id' => 'normal', 'values' => ['blur' => .5]]]));
        $this->assertGreaterThan(0, imagecolorat($blurred, 6, 7) & 255);
        $this->assertLessThan(255, imagecolorat($blurred, 7, 7) & 255);
        $this->assertSame(15, imagesx($blurred));
        imagedestroy($blurred);
    }

    public function test_blend_modes_and_saved_overlay_settings(): void
    {
        $compositor = app(\App\Services\ImageOverlay::class);
        $this->assertEqualsWithDelta([.1, .1, .1], $compositor->blend([.2,.4,.5], [.5,.25,.2], 'multiply'), .00001);
        $this->assertEqualsWithDelta([.6, .55, .6], $compositor->blend([.2,.4,.5], [.5,.25,.2], 'screen'), .00001);
        foreach (array_keys(app(ImageFilters::class)->catalog()['overlay']['blendModes']) as $mode) {
            foreach ($compositor->blend([.2,.4,.5], [.5,.25,.2], $mode) as $channel) $this->assertTrue(is_finite($channel) && $channel >= 0 && $channel <= 1, $mode);
        }
        $user = User::factory()->create(); $project = $this->project($user);
        $filter = ['id' => 'normal', 'values' => ['invert' => .4, 'blur' => 2.5], 'overlay' => ['type' => 'radial', 'blend' => 'soft-light', 'opacity' => .4, 'color1' => '#ffaa00', 'stop1' => 20]];
        $this->actingAs($user)->patchJson(route('projects.update', $project), ['image_filters' => [$filter]])->assertOk();
        $this->assertSame([$filter], $project->fresh()->image_filters);
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
        $project = $this->project($user, [['id' => 'normal', 'values' => ['invert' => .2, 'blur' => .5], 'overlay' => ['type' => 'linear', 'color1' => '#ff0000', 'color2' => '#0000ff', 'blend' => 'screen', 'opacity' => .5]]]);
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
