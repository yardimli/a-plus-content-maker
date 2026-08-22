<?php

use App\Http\Controllers\Admin\TemplateController as AdminTemplateController;
use App\Http\Controllers\Admin\TemplateAssetController;
use App\Http\Controllers\Admin\AiCallLogController;
use App\Http\Controllers\Admin\AiSettingsController;
use App\Http\Controllers\AiController;
use App\Http\Controllers\AsinController;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\BuilderController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectModuleController;
use App\Http\Controllers\TemplateGalleryController;
use Illuminate\Support\Facades\Route;

Route::get('/', LandingController::class)->name('home');
Route::get('/templates', [TemplateGalleryController::class, 'index'])->name('templates.index');
Route::get('/templates/{template}', [TemplateGalleryController::class, 'show'])->name('templates.show');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::get('/projects/create', [ProjectController::class, 'create'])->name('projects.create');
    Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::patch('/projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
    Route::delete('/projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');
    Route::get('/projects/{project}/builder', [BuilderController::class, 'show'])->name('projects.builder');
    Route::post('/projects/{project}/modules', [ProjectModuleController::class, 'store'])->name('projects.modules.store');
    Route::patch('/projects/{project}/modules/{module}', [ProjectModuleController::class, 'update'])->name('projects.modules.update');
    Route::delete('/projects/{project}/modules/{module}', [ProjectModuleController::class, 'destroy'])->name('projects.modules.destroy');
    Route::post('/projects/{project}/modules/reorder', [ProjectModuleController::class, 'reorder'])->name('projects.modules.reorder');
    Route::post('/projects/{project}/assets', [AssetController::class, 'store'])->name('projects.assets.store');
    Route::patch('/assets/{asset}', [AssetController::class, 'update'])->name('assets.update');
    Route::delete('/assets/{asset}', [AssetController::class, 'destroy'])->name('assets.destroy');
    Route::post('/api/asin/lookup', [AsinController::class, 'lookup'])->middleware('throttle:20,1')->name('asin.lookup');
    Route::post('/projects/{project}/asin/import', [AsinController::class, 'import'])->middleware('throttle:20,1')->name('projects.asin.import');
    Route::post('/projects/{project}/ai/text', [AiController::class, 'text'])->middleware('throttle:10,1')->name('projects.ai.text');
    Route::post('/projects/{project}/ai/image', [AiController::class, 'image'])->middleware('throttle:5,1')->name('projects.ai.image');
    Route::post('/projects/{project}/export', [ExportController::class, 'store'])->name('projects.export');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::prefix('admin')->name('admin.')->middleware('admin')->group(function () {
        Route::get('/ai/settings', [AiSettingsController::class, 'edit'])->name('ai.settings');
        Route::put('/ai/settings', [AiSettingsController::class, 'update'])->name('ai.settings.update');
        Route::get('/ai/logs', [AiCallLogController::class, 'index'])->name('ai.logs');
        Route::post('/template-assets', [TemplateAssetController::class, 'store'])->name('template-assets.store');
        Route::resource('templates', AdminTemplateController::class)->except('show');
    });
});

require __DIR__.'/auth.php';
