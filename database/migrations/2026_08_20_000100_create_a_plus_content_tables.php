<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('summary')->nullable();
            $table->text('description')->nullable();
            $table->string('category')->nullable()->index();
            $table->json('tags')->nullable();
            $table->string('preview_image')->nullable();
            $table->string('status')->default('draft')->index();
            $table->boolean('is_featured')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_template_id')->nullable()->constrained('templates')->nullOnDelete();
            $table->string('name');
            $table->string('status')->default('draft')->index();
            $table->string('marketplace')->default('amazon.com');
            $table->string('asin', 10)->nullable()->index();
            $table->json('product_snapshot')->nullable();
            $table->string('author_name')->nullable();
            $table->string('genre')->nullable();
            $table->string('audience')->nullable();
            $table->string('tone')->nullable();
            $table->text('brand_notes')->nullable();
            $table->timestamp('last_saved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('template_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained()->cascadeOnDelete();
            $table->string('module_type')->index();
            $table->unsignedInteger('position');
            $table->json('content');
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->unique(['template_id', 'position']);
        });

        Schema::create('project_modules', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('module_type')->index();
            $table->unsignedInteger('position');
            $table->json('content');
            $table->json('settings')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->unique(['project_id', 'position']);
        });

        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source')->default('upload');
            $table->string('disk')->default('public');
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->string('mime_type');
            $table->string('extension', 12)->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('alt_text')->nullable();
            $table->string('checksum', 64)->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('asin_lookups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('asin', 10)->index();
            $table->string('marketplace')->default('amazon.com');
            $table->boolean('was_successful')->default(false);
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->json('response_snapshot')->nullable();
            $table->string('error_code')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('ai_generations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_module_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind');
            $table->string('model');
            $table->text('prompt');
            $table->json('response_payload')->nullable();
            $table->string('status')->default('pending');
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('ready');
            $table->string('format')->default('kdp_transfer_json');
            $table->string('disk')->nullable();
            $table->string('path')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->json('manifest')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exports');
        Schema::dropIfExists('ai_generations');
        Schema::dropIfExists('asin_lookups');
        Schema::dropIfExists('assets');
        Schema::dropIfExists('project_modules');
        Schema::dropIfExists('template_modules');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('templates');
    }
};
