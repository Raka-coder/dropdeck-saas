<?php

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\PublicDropLinkController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['guest'])->group(function () {
    Route::get('auth/google/redirect', [GoogleAuthController::class, 'redirect'])
        ->name('auth.google.redirect');
    Route::get('auth/google/callback', [GoogleAuthController::class, 'callback'])
        ->name('auth.google.callback');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'pages.dashboard')->name('dashboard');

    Route::livewire('boards', 'pages::boards.index')->name('boards.index');
    Route::livewire('boards/{board}', 'pages::boards.show')->name('boards.show');
    Route::livewire('links', 'pages::links.index')->name('links.index');

    Route::get('attachments/{attachment}/download', [AttachmentController::class, 'download'])
        ->name('attachments.download');
    Route::get('attachments/{attachment}/thumbnail', [AttachmentController::class, 'thumbnail'])
        ->name('attachments.thumbnail');
});

Route::get('d/{token}', [PublicDropLinkController::class, 'show'])
    ->middleware('throttle:droplink-page')
    ->whereAlphaNumeric('token')
    ->name('droplink.show');

Route::post('d/{token}/unlock', [PublicDropLinkController::class, 'unlock'])
    ->middleware('throttle:droplink-password')
    ->whereAlphaNumeric('token')
    ->name('droplink.unlock');

Route::post('d/{token}/download', [PublicDropLinkController::class, 'download'])
    ->middleware('throttle:droplink-download')
    ->whereAlphaNumeric('token')
    ->name('droplink.download');

Route::post('d/{token}/download/{attachment}', [PublicDropLinkController::class, 'download'])
    ->middleware('throttle:droplink-download')
    ->whereAlphaNumeric(['token', 'attachment'])
    ->name('droplink.download.attachment');

require __DIR__.'/settings.php';
