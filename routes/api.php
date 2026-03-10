<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BusinessContextController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\MessageQuotaController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// ─── Auth (sin middleware) ────────────────────────────────────────────────────
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        $user = $request->user();
        return response()->json([
            'id'    => $user->id,
            'name'  => $user->name,
            'email' => $user->email,
            'roles' => $user->getRoleNames(),
        ]);
    });
    Route::post('/logout', [AuthController::class, 'logout']);
});

// ─── Webhooks (sin auth, llamados desde n8n) ─────────────────────────────────
Route::post('/webhook', [WebhookController::class, 'receive']);
Route::post('/webhook/outbound', [WebhookController::class, 'storeOutbound']);

// ─── Contexto del negocio (público para n8n) ─────────────────────────────────
Route::get('/business-context', [BusinessContextController::class, 'index']);

// ─── Rutas para admin y asesores ─────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {
    // Conversaciones (admin ve todas, asesor solo las suyas)
    Route::get('/conversations', [ConversationController::class, 'index']);
    Route::get('/conversations/{conversation}/messages', [ConversationController::class, 'messages']);
    Route::patch('/conversations/{conversation}/toggle-human', [ConversationController::class, 'toggleHuman']);
    Route::post('/conversations/{conversation}/send', [ConversationController::class, 'sendHuman']);
    Route::patch('/contacts/{contact}/name', [ContactController::class, 'updateName'])
        ->middleware('role:admin|asesor');

    // Estado de cuota mensual de mensajes
    Route::get('/message-quota', [MessageQuotaController::class, 'show']);
});

// ─── Rutas exclusivas de Admin ───────────────────────────────────────────────
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {
    // Gestión de asesores
    Route::get('/asesores', [UserController::class, 'index']);
    Route::get('/asesores/limits', [UserController::class, 'limits']);
    Route::post('/asesores', [UserController::class, 'store']);
    Route::put('/asesores/{user}', [UserController::class, 'update']);
    Route::delete('/asesores/{user}', [UserController::class, 'destroy']);
    // Asignar / desasignar conversación
    Route::post('/assign', [UserController::class, 'assignConversation']);
    Route::post('/unassign', [UserController::class, 'unassignConversation']);
    // CRUD contexto del negocio
    Route::post('/business-context', [BusinessContextController::class, 'store']);
    Route::put('/business-context/{businessContext}', [BusinessContextController::class, 'update']);
    Route::delete('/business-context/{businessContext}', [BusinessContextController::class, 'destroy']);
});
