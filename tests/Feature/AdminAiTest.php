<?php

namespace Tests\Feature;

use App\Models\AiSetting;
use App\Models\Asset;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminAiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.openrouter.key' => 'test-key', 'services.openrouter.base_url' => 'https://openrouter.test']);
        Cache::flush();
    }

    public function test_only_admin_can_view_live_model_prices_and_update_defaults(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create();
        Http::fake(['openrouter.test/models*' => Http::response(['data' => $this->models()], 200)]);

        $this->actingAs($user)->get(route('admin.ai.settings'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.ai.settings'))->assertOk()
            ->assertSee('Text Model')->assertSee('$1.00/1M')->assertSee('Image Model');

        $this->actingAs($admin)->put(route('admin.ai.settings.update'), [
            'text_model' => 'vendor/text-model', 'image_model' => 'vendor/image-model',
        ])->assertRedirect();

        $this->assertDatabaseHas('ai_settings', [
            'id' => 1, 'text_model' => 'vendor/text-model', 'image_model' => 'vendor/image-model', 'updated_by' => $admin->id,
        ]);
    }

    public function test_model_calls_log_cost_user_model_and_location_for_admin_audit(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);
        $project = Project::create(['uuid' => (string) Str::uuid(), 'user_id' => $user->id, 'name' => 'Logged project']);
        AiSetting::create(['text_model' => 'vendor/text-model', 'image_model' => 'vendor/image-model', 'updated_by' => $admin->id]);
        Http::fake(['openrouter.test/chat/completions' => Http::response([
            'id' => 'gen-test-123',
            'choices' => [['message' => ['content' => "```json\n".json_encode(['headline' => 'A new journey', 'body_html' => '<p>Begin here.</p>'])."\n```"]]],
            'usage' => ['prompt_tokens' => 120, 'completion_tokens' => 30, 'total_tokens' => 150, 'cost' => 0.00123456],
        ], 200)]);

        $this->actingAs($user)->postJson(route('projects.ai.text', $project), [
            'prompt' => 'Write an introduction', 'module_type' => 'standard_text',
        ])->assertOk()->assertJsonPath('data.headline', 'A new journey');

        $this->assertDatabaseHas('ai_call_logs', [
            'user_id' => $user->id, 'project_id' => $project->id, 'kind' => 'text', 'model' => 'vendor/text-model',
            'location' => 'builder.module_copy', 'status' => 'succeeded', 'input_tokens' => 120,
            'output_tokens' => 30, 'total_tokens' => 150, 'cost_usd' => 0.00123456, 'provider_request_id' => 'gen-test-123',
        ]);
        Http::assertSent(fn ($request) => $request->url() === 'https://openrouter.test/chat/completions'
            && $request['model'] === 'vendor/text-model'
            && ! isset($request['response_format']));

        $this->actingAs($user)->get(route('admin.ai.logs'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.ai.logs'))->assertOk()
            ->assertSee('vendor/text-model')->assertSee($user->email)->assertSee('Module Copy')->assertSee('$0.00123456');
    }

    public function test_image_generation_uses_the_dedicated_openrouter_image_api(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create();
        $project = Project::create(['uuid' => (string) Str::uuid(), 'user_id' => $user->id, 'name' => 'Image project']);
        AiSetting::create(['text_model' => 'vendor/text-model', 'image_model' => 'vendor/image-model', 'updated_by' => $admin->id]);
        $png = base64_encode(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='));

        Http::fake(['openrouter.test/images' => Http::response([
            'created' => 1748372400,
            'data' => [['b64_json' => $png, 'media_type' => 'image/png']],
            'usage' => ['prompt_tokens' => 12, 'completion_tokens' => 100, 'total_tokens' => 112, 'cost' => 0.04],
        ], 200)]);

        $this->actingAs($user)->postJson(route('projects.ai.image', $project), [
            'prompt' => 'A lighthouse at dusk', 'alt_text' => 'A lighthouse at dusk', 'width' => 970, 'height' => 300,
        ])->assertCreated()->assertJsonPath('data.source', 'ai');

        $asset = Asset::where('project_id', $project->id)->firstOrFail();
        Storage::disk('public')->assertExists($asset->path);
        $this->assertSame('image/png', $asset->mime_type);
        $this->assertDatabaseHas('ai_call_logs', [
            'user_id' => $user->id, 'kind' => 'image', 'model' => 'vendor/image-model', 'location' => 'builder.image_generation',
            'status' => 'succeeded', 'cost_usd' => 0.04,
        ]);
        Http::assertSent(fn ($request) => $request->url() === 'https://openrouter.test/images'
            && $request['model'] === 'vendor/image-model'
            && $request['prompt'] !== null
            && $request['n'] === 1
            && $request['output_format'] === 'png'
            && ! isset($request['modalities'])
            && ! isset($request['messages']));
    }

    private function models(): array
    {
        return [
            ['id' => 'vendor/text-model', 'name' => 'Text Model', 'architecture' => ['output_modalities' => ['text']], 'pricing' => ['prompt' => '0.000001', 'completion' => '0.000002', 'request' => '0', 'image' => '0']],
            ['id' => 'vendor/image-model', 'name' => 'Image Model', 'architecture' => ['output_modalities' => ['image']], 'pricing' => ['prompt' => '0.000003', 'completion' => '0.000004', 'request' => '0.01', 'image' => '0.02']],
        ];
    }
}
