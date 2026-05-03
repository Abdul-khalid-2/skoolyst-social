<?php

use App\Http\Controllers\AccountsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CreatePostController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FacebookDataDeletionController;
use App\Http\Controllers\PostsController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SettingsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::get('/privacy/data-deletion', [FacebookDataDeletionController::class, 'show'])
    ->name('privacy.data-deletion');

Route::middleware('guest')->group(function (): void {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login']);
    Route::get('register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('register', [AuthController::class, 'register']);
});

Route::middleware(['auth', 'workspace.context'])->group(function (): void {
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('create', [CreatePostController::class, 'index'])->name('create');
    Route::get('posts', [PostsController::class, 'index'])->name('posts.index');
    Route::get('scheduled', [PostsController::class, 'scheduled'])->name('posts.scheduled');
    Route::delete('posts/{post}', [PostsController::class, 'destroy'])->name('posts.destroy');
    Route::get('accounts', [AccountsController::class, 'index'])->name('accounts');
    Route::delete('accounts/connections/{account}', [AccountsController::class, 'destroyConnection'])->name('accounts.connections.destroy');
    Route::view('analytics', 'analytics.index');
    Route::get('settings', [SettingsController::class, 'index'])->name('settings');
    Route::put('settings/workspace', [SettingsController::class, 'updateWorkspace'])->name('settings.workspace');
    Route::post('settings/facebook-data', [SettingsController::class, 'destroyFacebookData'])->name('settings.facebook-data.destroy');
    Route::get('profile', [ProfileController::class, 'index'])->name('profile');
    Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::view('switch-account', 'workspaces.switch');
    Route::view('notifications', 'notifications.index');
    Route::get('activity', [\App\Http\Controllers\ActivityController::class, 'index'])->name('activity');
});

// Temporary testing route: allows logout via direct URL hit.
Route::get('logout', function (Request $request): \Illuminate\Http\RedirectResponse {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->route('login');
})->name('logout.get');
