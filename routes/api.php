<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SMSController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\DonorController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EnvelopeJubileeController;

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    $user = $request->user();
    $user->role_names = $user->roles->pluck('name')->toArray();
    return $user;
});

Route::group(['middleware' => ['auth', 'role:admin|ancien|universitaire']], function () {
    Route::post('/send-sms', [SMSController::class, "send"]);
    Route::get('/sms-balance', [SMSController::class, "getBalanceSMS"]);
    Route::get('/sms-template/{id}', [SMSController::class, "getSMSTemplate40e"]);
    Route::put('/sms-template/{id}', [SMSController::class, "updateSMSTemplate40e"]);
    Route::delete('/sms-template/{id}', [SMSController::class, "deleteSMSTemplate40e"]);
    Route::post('/sms-template', [SMSController::class, "createSMSTemplate40e"]);
    Route::get('/sms-templates', [SMSController::class, "getSMSTemplates"]);
});

Route::group(['middleware' => ['auth']], function () {
    Route::get('/donors/{id}', [DonorController::class, "show"]);
    Route::put('/donors/{id}', [DonorController::class, "update"]);
    Route::delete('/donors/{id}', [DonorController::class, "destroy"]);
    Route::post('/donors', [DonorController::class, "store"]);
    Route::get('/donors', [DonorController::class, "index"]);
});

Route::group(['middleware' => ['auth', 'role:admin|ancien|universitaire']], function () {
    Route::get('/dashboard/data', [DashboardController::class, "getMainData"]);
    Route::get('/users/list', [UserController::class, "getUserList"]);
    Route::get('/users/list-options', [UserController::class, "getUserListForOptions"]);
    Route::post('/users/delete-selected', [UserController::class, "deleteUser"]);
    Route::post('/users/mark-sepecial-elder-selected', [UserController::class, "makeSpecialElders"]);
    Route::put('/users/update/{id}', [UserController::class, "updateInfoElder"]);
    Route::get('/user/{id}', [UserController::class, "getUser"]);
    Route::get('/generate-fictive-email', [UserController::class, "generateFictiveEmail"]);
});

Route::group(['middleware' => ['auth', 'role:admin']], function () {
    Route::post('/elder/upload-excel', [UserController::class, 'upload']);
});

Route::post('/elder/check-my-info', [UserController::class, 'getUserForChecking']);

Route::group(['middleware' => ['auth', 'role:admin|ancien|universitaire']], function () {
    Route::post('/jubilee/envelope-create/{forId}', [EnvelopeJubileeController::class, "createEnvelope"]);
    Route::get('/jubilee/envelope/{id}', [EnvelopeJubileeController::class, "getEnvelope"]);
    Route::post('/jubilee/envelope-generate-file/{id}', [EnvelopeJubileeController::class, "generateEnvelopePdf"]);
    Route::put('/jubilee/update-envelope/{id}', [EnvelopeJubileeController::class, "updateEnvelope"]);
    Route::get('/jubilee/list-envelope', [EnvelopeJubileeController::class, "getEnvelopeList"]);
    Route::delete('/jubilee/envelope-delete/{id}', [EnvelopeJubileeController::class, "deleteEnvelope"]);
    Route::post('/jubilee/envelopes-delete', [EnvelopeJubileeController::class, "deleteEnvelopes"]);
    Route::post('/jubilee/envelope-qr-code/{id}', [EnvelopeJubileeController::class, "envelopeCreateQrCode"]);
    Route::post('/jubilee/envelope/generate-file-bulk', [EnvelopeJubileeController::class, "generateEnvelopePdfBulk"]);
    Route::get('/jubilee/envelope-comments', [EnvelopeJubileeController::class, "getAllComents"]);
    Route::post('/jubilee-send-envelope-by-email', [EnvelopeJubileeController::class, "sendEnvelopeByEmail"]);
    Route::get('/jubilee/envelopes-grouped-by-week', [EnvelopeJubileeController::class, 'listGroupedByWeek']);
    Route::get('/jubilee/envelopes-grouped-by-responsible', [EnvelopeJubileeController::class, 'listGroupedByResponsible']);
    Route::post('/jubilee/update-earning', [EnvelopeJubileeController::class, "updateEarning"]);
});

Route::post('/jubilee/envelope-validate', [EnvelopeJubileeController::class, "validateEnvelope"]);

Route::group(['middleware' => ['auth', 'role:admin|ancien|universitaire']], function () {
    Route::get('/majors', [StudentController::class, "getMajorList"]);
    Route::get('/levels', [StudentController::class, "getLevelList"]);
    Route::get('/student-options', [StudentController::class, "getStudentListForOptions"]);
    Route::get('/last-census/content', [StudentController::class, "getCensusContent"]);
    Route::get('/student/{id}', [StudentController::class, "getSudentPerso"]);
    Route::get('/census/{id}', [StudentController::class, "getCensusById"]);
});

Route::group(['middleware' => ['auth', 'role:admin|universitaire']], function () {
    Route::post('/student/sign-up', [StudentController::class, "signUpStudent"]);
    Route::put('/student/update-info-perso', [StudentController::class, "updateStudentInfoPerso"]);
    Route::post('/student/make-census', [StudentController::class, "makeCensus"]);
    Route::put('/student/update-census/{id}', [StudentController::class, "updateCensus"]);
});


Route::group(['middleware' => ['auth', 'role:admin']], function () {
    Route::post('/create-pwd-account', [UserController::class, "createPwdForUser"]);
});


Route::get('/health', function () {
    return response()->json(['status' => 'alive']);
});

require __DIR__ . '/auth.php';
