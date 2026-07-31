<?php

use App\Http\Controllers\SitemapResourceController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    // The host-owned Frame resource edit page for the editable sitemap (kind A).
    // Frame ships only frame/manifest; the host binds each resource's edit route.
    Route::get('frame/resources/sitemap', SitemapResourceController::class)
        ->name('frame.resources.sitemap');
});

require __DIR__.'/settings.php';
