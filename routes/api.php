<?php
use App\Http\Controllers\{DestinationSchemaController,ImportController,ImportOperationsController,MappingController,OperationsController,UploadController};use Illuminate\Support\Facades\Route;
Route::get('/health',fn()=>['ok'=>true,'service'=>'dataforge']);
Route::prefix('auth')->group(function(){Route::post('/register',[\App\Http\Controllers\Auth\AuthController::class,'register']);Route::post('/login',[\App\Http\Controllers\Auth\AuthController::class,'login']);Route::middleware('auth:sanctum')->get('/me',[\App\Http\Controllers\Auth\AuthController::class,'me']);Route::middleware('auth:sanctum')->delete('/logout',[\App\Http\Controllers\Auth\AuthController::class,'logout']);});
Route::middleware('auth:sanctum')->group(function(){
 Route::get('/schemas',[DestinationSchemaController::class,'index']);Route::post('/schemas',[DestinationSchemaController::class,'store']);
 Route::post('/uploads/prepare',[UploadController::class,'prepare']);Route::post('/uploads/finalize',[UploadController::class,'finalize']);
 Route::get('/imports',[ImportController::class,'index']);Route::post('/imports',[ImportController::class,'store']);Route::get('/imports/{import}',[ImportController::class,'show']);Route::delete('/imports/{import}',[ImportController::class,'destroy']);
 Route::get('/organizations/{organizationId}/metrics',[OperationsController::class,'metrics']);
 Route::get('/imports/{import}/mapping',[MappingController::class,'show']);Route::put('/imports/{import}/mapping',[MappingController::class,'save']);Route::post('/imports/{import}/preview',[ImportOperationsController::class,'preview']);
 Route::post('/imports/{import}/executions',[ImportController::class,'execute']);Route::get('/imports/{import}/executions/{execution}',[ImportOperationsController::class,'execution']);Route::post('/imports/{import}/executions/{executionId}/cancel',[ImportController::class,'cancel']);Route::post('/imports/{import}/executions/{execution}/resume',[ImportOperationsController::class,'resume']);Route::get('/imports/{import}/executions/{execution}/errors',[ImportOperationsController::class,'errorRows']);Route::get('/imports/{import}/executions/{execution}/errors.csv',[ImportOperationsController::class,'errors']);
});
