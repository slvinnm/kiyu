<?php

use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\ClinicalQueueController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\IntakeController;
use App\Http\Controllers\Api\PatientController;
use App\Http\Controllers\Api\PublicQueueController;
use App\Http\Controllers\Api\QueueAcquisitionController;
use App\Http\Controllers\Api\ReceptionController;
use App\Http\Controllers\Api\ReferralController;
use App\Http\Controllers\Api\StationController;
use App\Http\Controllers\Api\TicketController;
use App\Http\Controllers\Api\VisitController;
use App\Http\Controllers\Api\WorkflowController;
use Illuminate\Support\Facades\Route;

// 1. Intake Channel Endpoints
Route::prefix('intake')->group(function () {
    Route::post('/kiosk', [IntakeController::class, 'kiosk']);
    Route::post('/manual', [IntakeController::class, 'manual']);
    Route::post('/online', [IntakeController::class, 'online']);
    Route::get('/online-lookup/{identifier}', [IntakeController::class, 'onlineLookup']);
    Route::post('/online-checkin', [IntakeController::class, 'onlineCheckin']);
});

// 1b. Queue Acquisition Management
Route::prefix('queue-acquisitions')->group(function () {
    Route::get('/', [QueueAcquisitionController::class, 'index']);
    Route::get('/{id}', [QueueAcquisitionController::class, 'show']);
    Route::post('/{id}/cancel', [QueueAcquisitionController::class, 'cancel']);
});

// 2. Reception Queue & Registration Endpoints
Route::prefix('reception')->group(function () {
    Route::get('/queue', [ReceptionController::class, 'queue']);

    Route::post('/call-next', [ReceptionController::class, 'callNext']);
    Route::post('/complete-registration', [ReceptionController::class, 'completeRegistration']);
    Route::post('/{ticketId}/recall', [ReceptionController::class, 'recall']);
    Route::post('/{ticketId}/skip', [ReceptionController::class, 'skip']);
    Route::post('/{ticketId}/no-show', [ReceptionController::class, 'noShow']);
});

// 3. Clinical Queue Operations
Route::prefix('clinical')->group(function () {
    Route::post('/call-next', [ClinicalQueueController::class, 'callNext']);
});

Route::prefix('tickets')->group(function () {
    Route::get('/', [TicketController::class, 'index']);
    Route::get('/{id}', [TicketController::class, 'show']);
    Route::get('/{id}/events', [TicketController::class, 'events']);

    Route::post('/{id}/start', [ClinicalQueueController::class, 'start']);
    Route::post('/{id}/hold', [ClinicalQueueController::class, 'hold']);
    Route::post('/{id}/resume', [ClinicalQueueController::class, 'resume']);
    Route::post('/{id}/recall', [ClinicalQueueController::class, 'recall']);
    Route::post('/{id}/complete', [ClinicalQueueController::class, 'complete']);
    Route::post('/{id}/skip', [ClinicalQueueController::class, 'skip']);
    Route::post('/{id}/no-show', [ClinicalQueueController::class, 'noShow']);
    Route::post('/{id}/cancel', [ClinicalQueueController::class, 'cancel']);
    Route::post('/{id}/transfer', [ClinicalQueueController::class, 'transfer']);
});

// 4. Referral Management
Route::prefix('referrals')->group(function () {
    Route::get('/', [ReferralController::class, 'index']);
    Route::post('/', [ReferralController::class, 'store']);
    Route::get('/{id}', [ReferralController::class, 'show']);
});

// 5. Public Queue Display (No PII)
Route::get('/public/queue', [PublicQueueController::class, 'index']);

// 6. Visit Management
Route::prefix('visits')->group(function () {
    Route::get('/', [VisitController::class, 'index']);
    Route::get('/{id}', [VisitController::class, 'show']);
    Route::patch('/{id}/priority', [VisitController::class, 'updatePriority']);
    Route::post('/{id}/cancel', [VisitController::class, 'cancel']);
});

// 7. Organizational Setup (Departments, Stations, Workflows)
Route::prefix('departments')->group(function () {
    Route::get('/active', [DepartmentController::class, 'active']);
    Route::get('/', [DepartmentController::class, 'index']);
    Route::post('/', [DepartmentController::class, 'store']);
    Route::get('/{id}', [DepartmentController::class, 'show']);
    Route::put('/{id}', [DepartmentController::class, 'update']);
    Route::get('/{id}/stations', [DepartmentController::class, 'stations']);
    Route::get('/{id}/workflows', [DepartmentController::class, 'workflows']);
});

Route::prefix('stations')->group(function () {
    Route::get('/', [StationController::class, 'index']);
    Route::post('/', [StationController::class, 'store']);
    Route::get('/{id}', [StationController::class, 'show']);
    Route::put('/{id}', [StationController::class, 'update']);
    Route::get('/{id}/queue', [StationController::class, 'queue']);
});

Route::prefix('workflows')->group(function () {
    Route::get('/', [WorkflowController::class, 'index']);
    Route::post('/', [WorkflowController::class, 'store']);
    Route::get('/{id}', [WorkflowController::class, 'show']);
    Route::post('/{id}/steps', [WorkflowController::class, 'addStep']);
});

Route::prefix('workflow-steps')->group(function () {
    Route::put('/{id}', [WorkflowController::class, 'updateStep']);
    Route::delete('/{id}', [WorkflowController::class, 'deleteStep']);
});

// 8. Patient Directory
Route::prefix('patients')->group(function () {
    Route::get('/', [PatientController::class, 'index']);
    Route::post('/', [PatientController::class, 'store']);
    Route::get('/{id}', [PatientController::class, 'show']);
    Route::put('/{id}', [PatientController::class, 'update']);
    Route::get('/{id}/visits', [PatientController::class, 'visits']);
});

// 9. Audit Logs
Route::get('/audit-logs', [AuditLogController::class, 'index']);
