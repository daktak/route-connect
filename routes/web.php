<?php

use App\Http\Controllers\CommentController;
use App\Http\Controllers\FeatureController;
use App\Http\Controllers\GpxController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RatingController;
use App\Http\Controllers\RideAttendeeController;
use App\Http\Controllers\RideController;
use App\Http\Controllers\RouteController;
use Illuminate\Support\Facades\Route;

// Home redirects to routes
Route::get('/', function () {
    return redirect()->route('routes.index');
});

// Routes
Route::get('/routes', [RouteController::class, 'index'])->name('routes.index');
Route::get('/routes/map', [RouteController::class, 'map'])->name('routes.map');
Route::get('/routes/create', [RouteController::class, 'create'])->name('routes.create')->middleware('auth');
Route::post('/routes', [RouteController::class, 'store'])->name('routes.store')->middleware('auth');
Route::get('/routes/{route}/download', [RouteController::class, 'download'])->name('routes.download');
Route::get('/routes/{route}', [RouteController::class, 'show'])->name('routes.show');
Route::get('/routes/{route}/edit', [RouteController::class, 'edit'])->name('routes.edit')->middleware('auth');
Route::put('/routes/{route}', [RouteController::class, 'update'])->name('routes.update')->middleware('auth');
Route::delete('/routes/{route}', [RouteController::class, 'destroy'])->name('routes.destroy')->middleware('auth');

// Route Ratings
Route::post('/routes/{route}/rate', [RatingController::class, 'store'])->name('routes.rate')->middleware('auth');

// Route Comments
Route::post('/routes/{route}/comments', [CommentController::class, 'store'])->name('routes.comments.store')->middleware('auth');
Route::put('/comments/{comment}', [CommentController::class, 'update'])->name('comments.update')->middleware('auth');
Route::delete('/comments/{comment}', [CommentController::class, 'destroy'])->name('comments.destroy')->middleware('auth');

// Route Features
Route::post('/routes/{route}/features', [FeatureController::class, 'store'])->name('routes.features.store')->middleware('auth');
Route::delete('/features/{feature}', [FeatureController::class, 'destroy'])->name('features.destroy')->middleware('auth');

// Group Rides
Route::get('/rides', [RideController::class, 'index'])->name('rides.index');
Route::get('/rides/create', [RideController::class, 'create'])->name('rides.create')->middleware('auth');
Route::post('/rides', [RideController::class, 'store'])->name('rides.store')->middleware('auth');
Route::get('/rides/{ride}', [RideController::class, 'show'])->name('rides.show');
Route::get('/rides/{ride}/edit', [RideController::class, 'edit'])->name('rides.edit')->middleware('auth');
Route::put('/rides/{ride}', [RideController::class, 'update'])->name('rides.update')->middleware('auth');
Route::delete('/rides/{ride}', [RideController::class, 'destroy'])->name('rides.destroy')->middleware('auth');

// Ride Attendees
Route::post('/rides/{ride}/join', [RideAttendeeController::class, 'join'])->name('rides.join')->middleware('auth');
Route::delete('/rides/{ride}/leave', [RideAttendeeController::class, 'leave'])->name('rides.leave')->middleware('auth');
Route::get('/rides/{ride}/attendee-status', [RideAttendeeController::class, 'status'])->name('rides.attendee.status')->middleware('auth');

// Notifications
Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index')->middleware('auth');
Route::post('/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read')->middleware('auth');
Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all')->middleware('auth');

// GPX API
Route::post('/api/routes/parse-gpx', [GpxController::class, 'parse'])->name('api.routes.parse-gpx');

// Profile (Breeze)
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
