Route::prefix('wfh')->group(function () {
    Route::get('/', [\App\Http\Controllers\Api\WfhRequestController::class, 'index']);
    Route::get('/available-saturdays', [\App\Http\Controllers\Api\WfhRequestController::class, 'availableSaturdays']);
    Route::get('/my-requests', [\App\Http\Controllers\Api\WfhRequestController::class, 'myRequests']);
    Route::get('/team-requests', [\App\Http\Controllers\Api\WfhRequestController::class, 'teamRequests']);
    Route::post('/', [\App\Http\Controllers\Api\WfhRequestController::class, 'store']);
    Route::put('/{id}/approve', [\App\Http\Controllers\Api\WfhRequestController::class, 'approve']);
    Route::put('/{id}/reject', [\App\Http\Controllers\Api\WfhRequestController::class, 'reject']);
    Route::delete('/{id}', [\App\Http\Controllers\Api\WfhRequestController::class, 'cancel']);
});
