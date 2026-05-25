<?php

declare(strict_types=1);

use App\Modules\Education\UI\Http\Controller\Admin\ShowAdminDashboardController;
use App\Modules\Education\UI\Http\Controller\ShowHomeController;
use App\Modules\Education\UI\Http\Controller\ShowLessonController;
use App\Modules\Education\UI\Http\Controller\ShowLessonIndexController;
use Illuminate\Support\Facades\Route;

Route::get('/', ShowHomeController::class)->name('home');
Route::get('/lessons', ShowLessonIndexController::class)->name('lessons.index');
Route::get('/lessons/{slug}', ShowLessonController::class)
    ->where('slug', '[a-z0-9-]+')
    ->name('lessons.show');

// Admin dashboard (initial render via Inertia; polling JSON см. routes/api.php).
Route::get('/admin', ShowAdminDashboardController::class)->name('admin.dashboard');
