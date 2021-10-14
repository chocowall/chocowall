<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ApiController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/



Route::group(['as' => 'api.'], function () {
    Route::group(['middleware' => ['auth.nuget', 'file.nuget:package']], function () {
        Route::put('/upload', [ApiController::class, 'upload'])->name('upload');
        Route::put('/', [ApiController::class, 'upload'])->name('upload');
    });

    Route::group(['middleware' => ['auth.nuget']], function () {
        Route::delete('/{id}/{version}', [ApiController::class, 'delete'])->name('delete');
    });

    Route::group(['middleware' => ['auth.basic']], function () {
        Route::get('/download/{id}/{version}', [ApiController::class, 'download'])->name('download');
        Route::get('/download/{id}', [ApiController::class, 'download'])->name('download');
    });

    Route::group(['prefix' => '/v2'], function () {

        Route::group(['prefix' => '/download', 'middleware' => ['auth.choco']], function () {
            Route::get('/{id}/{version}', [ApiController::class, 'download'])->name('download');
            Route::get('/{id}', [ApiController::class, 'download'])->name('download');
        });

        Route::group(['prefix' => '/package'], function () {

            Route::group([ 'middleware' => ['auth.choco']], function () {
                Route::get('/{id}/{version}', [ApiController::class, 'download'])->name('download');
                Route::get('/{id}', [ApiController::class, 'download'])->name('download');
            });

            Route::group([ 'middleware' => ['auth.nuget']], function () {
                Route::put('/{id}', [ApiController::class, 'update'])->name('update');
                Route::delete('/{id}/{version}', [ApiController::class, 'delete'])->name('delete');
            });
        });

        Route::group(['middleware' => ['auth.nuget', 'file.nuget:package']], function () {
            Route::put('/upload', [ApiController::class, 'upload'])->name('upload');
            Route::put('/', [ApiController::class, 'upload'])->name('upload');
            Route::put('/package', [ApiController::class, 'upload'])->name('upload');
        });

        Route::group(['middleware' => ['auth.choco']], function () {
            Route::get('/',  [ApiController::class, 'index'])->name('index');
            Route::get('$metadata', [ApiController::class, 'metadata'])->name('metadata');
            Route::get('Packages()', [ApiController::class, 'packages'])->name('packages');
            Route::get('Packages', [ApiController::class, 'packages'])->name('packages');
            Route::get('GetUpdates()', [ApiController::class, 'updates'])->name('updates');
            Route::get('GetUpdates', [ApiController::class, 'updates'])->name('updates');
            Route::get('Search()/{action}', [ApiController::class, 'search'])->name('search.action');
            Route::get('Search()', [ApiController::class, 'searchNoAction'])->name('search');
            Route::get('Search', [ApiController::class, 'searchNoAction'])->name('search');
            Route::get('FindPackagesById()', [ApiController::class, 'packages'])->name('findById');
            Route::get('Packages(Id=\'{id}\',Version=\'{version}\')', [ApiController::class, 'package'])->name('package');

        });
    });
});
