<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\SuggestionController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\RatingController;

Route::middleware('throttle:60,1')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/auth/google', [AuthController::class, 'googleLogin']);
    Route::post('/email/resend', [AuthController::class, 'resendVerificationEmail']);
});

Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
    ->middleware('throttle:10,1')
    ->name('verification.verify');

Route::middleware(['throttle:60,1', 'api.token.optional'])->group(function () {
    Route::get('/posts', [PostController::class, 'index']);
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/users/{user}/posts', [PostController::class, 'userPosts']);
});

Route::middleware('api.token')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    // Media Tracking
    Route::post('/track/view', [PostController::class, 'trackView']);

    // Suggestions (Mock AI)
    Route::get('/suggestions/price', [SuggestionController::class, 'getPriceSuggestion']);
    Route::get('/suggestions/description', [SuggestionController::class, 'getDescriptionSuggestion']);

    // Posts
    Route::post('/posts', [PostController::class, 'store']);

    // Chat
    Route::post('/chat/send', [ChatController::class, 'sendMessage']);
    Route::get('/chat/messages/{user}', [ChatController::class, 'getMessages']);
    Route::get('/chat/conversations', [ChatController::class, 'getConversations']);
    Route::get('/users/phone/{phone}', [ChatController::class, 'findUserByPhone']);

    // Profile
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::post('/profile/update', [ProfileController::class, 'update']);
    Route::get('/profile/history', [ProfileController::class, 'history']);

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index']);

    // Social
    Route::post('/follow/{user}', [ProfileController::class, 'toggleFollow']);
    Route::post('/posts/{post}/like', [PostController::class, 'toggleLike']);
    Route::get('/posts/{post}/comments', [PostController::class, 'getComments']);
    Route::post('/posts/{post}/comments', [PostController::class, 'addComment']);

    // ── Orders ────────────────────────────────────────────────────────────────
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::post('/orders/{order}/accept', [OrderController::class, 'acceptOrder']);
    Route::post('/orders/{order}/confirm', [OrderController::class, 'confirmReceipt']);
    Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel']);

    // ── Payments & Escrow ─────────────────────────────────────────────────────
    Route::post('/orders/{order}/pay', [PaymentController::class, 'initiate'])->middleware('throttle:10,1');
    Route::post('/orders/{order}/refund', [PaymentController::class, 'refund']);
    Route::get('/orders/{order}/escrow', [PaymentController::class, 'escrowStatus']);

    // ── Ratings ───────────────────────────────────────────────────────────────
    Route::post('/orders/{order}/rate', [RatingController::class, 'store']);
    // ── Favorites ─────────────────────────────────────────────────────────────
    Route::post('/favorites/{product}/toggle', [\App\Http\Controllers\FavoriteController::class, 'toggle']);
    Route::get('/favorites', [\App\Http\Controllers\FavoriteController::class, 'index']);

    // ── Seller Dashboard ──────────────────────────────────────────────────────
    Route::get('/seller/stats', [\App\Http\Controllers\SellerDashboardController::class, 'stats']);
    Route::get('/seller/products', [\App\Http\Controllers\SellerDashboardController::class, 'activeProducts']);

    // ── Boost & Repost ────────────────────────────────────────────────────────
    Route::post('/products/{product}/repost', [\App\Http\Controllers\BoostController::class, 'repost']);
    Route::post('/products/{product}/boost', [\App\Http\Controllers\BoostController::class, 'boost']);

    // ── Search & Discovery ────────────────────────────────────────────────────
    Route::get('/search/products', [\App\Http\Controllers\ProductSearchController::class, 'search']);
    Route::get('/search/price-analytics', [\App\Http\Controllers\ProductSearchController::class, 'priceAnalytics']);
});
