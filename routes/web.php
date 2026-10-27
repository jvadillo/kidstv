<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminListController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Auth\ConfirmController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Panel\AccountController;
use App\Http\Controllers\Panel\ActivityController;
use App\Http\Controllers\Panel\ContentController;
use App\Http\Controllers\Panel\DashboardController;
use App\Http\Controllers\Panel\DeviceController;
use App\Http\Controllers\Panel\ListController;
use App\Http\Controllers\Panel\ProfileController;
use App\Http\Controllers\Panel\UploadController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::view('/privacidad', 'legal.privacy')->name('privacy');
Route::view('/condiciones', 'legal.terms')->name('terms');
Route::view('/ayuda/televisores', 'legal.tv-help')->name('help.tv');

Route::middleware('guest')->group(function () {
    Route::get('/entrar', [LoginController::class, 'show'])->name('login');
    Route::post('/entrar', [LoginController::class, 'send'])->middleware('throttle:login-request')->name('login.send');
    Route::get('/entrar/codigo', [LoginController::class, 'showCode'])->name('login.code');
    Route::post('/entrar/codigo', [LoginController::class, 'verifyCode'])->middleware('throttle:login-verify')->name('login.verify');
    Route::post('/entrar/reenviar', [LoginController::class, 'resend'])->middleware('throttle:login-request')->name('login.resend');
    Route::get('/entrar/enlace/{token}', [LoginController::class, 'showLink'])->name('login.link');
    Route::post('/entrar/enlace/{token}', [LoginController::class, 'consumeLink'])->middleware('throttle:login-verify')->name('login.link.consume');
    Route::get('/alta', [RegisterController::class, 'show'])->name('register');
    Route::post('/alta', [RegisterController::class, 'store'])->name('register.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/salir', [LoginController::class, 'logout'])->name('logout');

    Route::get('/confirmar', [ConfirmController::class, 'show'])->name('confirm.show');
    Route::post('/confirmar/enviar', [ConfirmController::class, 'send'])->middleware('throttle:confirm-send')->name('confirm.send');
    Route::post('/confirmar', [ConfirmController::class, 'verify'])->middleware('throttle:login-verify')->name('confirm.verify');

    // Destino del QR que muestra el televisor.
    Route::get('/tv/vincular', fn () => redirect()->route('devices.pair', request()->only('code')))->name('tv.link');

    // Administración de la plataforma (solo cuentas con is_admin).
    Route::prefix('admin')->middleware('admin')->name('admin.')->group(function () {
        Route::get('/', AdminDashboardController::class)->name('dashboard');
        Route::get('/usuarios', [AdminUserController::class, 'index'])->name('users.index');
        Route::get('/usuarios/{user}', [AdminUserController::class, 'show'])->name('users.show');
        Route::post('/usuarios/{user}/bloqueo', [AdminUserController::class, 'block'])->name('users.block');
        Route::delete('/usuarios/{user}/bloqueo', [AdminUserController::class, 'unblock'])->name('users.unblock');
        Route::put('/usuarios/{user}/administrador', [AdminUserController::class, 'admin'])->name('users.admin');
        Route::delete('/usuarios/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');
        Route::get('/listas', [AdminListController::class, 'index'])->name('lists.index');
        Route::post('/listas', [AdminListController::class, 'store'])->name('lists.store');
        Route::put('/listas/{list}', [AdminListController::class, 'update'])->name('lists.update');
        Route::post('/listas/{list}/publicar', [AdminListController::class, 'publish'])->name('lists.publish');
        Route::delete('/listas/{list}/publicar', [AdminListController::class, 'unpublish'])->name('lists.unpublish');
    });

    Route::prefix('panel')->group(function () {
        Route::get('/', DashboardController::class)->name('panel');
        Route::get('/bienvenida', [DashboardController::class, 'welcome'])->name('welcome');

        Route::get('/perfiles', [ProfileController::class, 'index'])->name('profiles.index');
        Route::get('/perfiles/nuevo', [ProfileController::class, 'create'])->name('profiles.create');
        Route::post('/perfiles', [ProfileController::class, 'store'])->name('profiles.store');
        Route::get('/perfiles/{profile}', [ProfileController::class, 'edit'])->name('profiles.edit');
        Route::put('/perfiles/{profile}', [ProfileController::class, 'update'])->name('profiles.update');
        Route::put('/perfiles/{profile}/contenidos', [ProfileController::class, 'assign'])->name('profiles.assign');
        Route::delete('/perfiles/{profile}', [ProfileController::class, 'destroy'])->name('profiles.destroy');

        Route::get('/listas', [ListController::class, 'index'])->name('lists.index');
        Route::post('/listas', [ListController::class, 'store'])->name('lists.store');
        Route::get('/listas/publicas', [ListController::class, 'explore'])->name('lists.public');
        Route::get('/listas/publicas/{list}', [ListController::class, 'showPublic'])->name('lists.public.show');
        Route::post('/listas/publicas/{list}', [ListController::class, 'subscribe'])->name('lists.public.subscribe');
        Route::get('/listas/publicas/{list}/portada/{content}', [ListController::class, 'publicCover'])->name('lists.public.cover');
        Route::get('/listas/sin-lista', [ListController::class, 'unlisted'])->name('lists.unlisted');
        Route::get('/listas/{list}', [ListController::class, 'show'])->whereNumber('list')->name('lists.show');
        Route::put('/listas/{list}', [ListController::class, 'update'])->name('lists.update');
        Route::put('/listas/{list}/perfiles', [ListController::class, 'profiles'])->name('lists.profiles');
        Route::post('/listas/{list}/mover', [ListController::class, 'move'])->name('lists.move');
        Route::delete('/listas/{list}', [ListController::class, 'destroy'])->name('lists.destroy');

        Route::get('/contenidos', [ContentController::class, 'index'])->name('contents.index');
        Route::get('/contenidos/nuevo/youtube', [ContentController::class, 'createYouTube'])->name('contents.youtube.create');
        Route::get('/contenidos/buscar-youtube', [ContentController::class, 'searchYouTube'])->name('contents.youtube.search');
        Route::post('/contenidos/youtube/comprobar', [ContentController::class, 'previewYouTube'])->name('contents.youtube.preview');
        Route::post('/contenidos/youtube', [ContentController::class, 'storeYouTube'])->name('contents.youtube.store');
        Route::get('/contenidos/nuevo/{kind}', [ContentController::class, 'createUpload'])->whereIn('kind', ['video', 'audio'])->name('contents.upload.create');
        Route::get('/contenidos/{content}', [ContentController::class, 'edit'])->name('contents.edit');
        Route::put('/contenidos/{content}', [ContentController::class, 'update'])->name('contents.update');
        Route::delete('/contenidos/{content}', [ContentController::class, 'destroy'])->name('contents.destroy');
        Route::post('/contenidos/{content}/mover', [ContentController::class, 'move'])->name('contents.move');
        Route::post('/contenidos/{content}/visibilidad', [ContentController::class, 'visibility'])->name('contents.visibility');
        Route::get('/contenidos/{content}/portada', [ContentController::class, 'cover'])->name('contents.cover');
        Route::get('/contenidos/{content}/archivo', [ContentController::class, 'file'])->name('contents.file');

        Route::post('/subidas', [UploadController::class, 'start'])->name('uploads.start');
        Route::post('/subidas/{upload}/fragmento', [UploadController::class, 'chunk'])->name('uploads.chunk');
        Route::post('/subidas/{upload}/completar', [UploadController::class, 'complete'])->name('uploads.complete');
        Route::delete('/subidas/{upload}', [UploadController::class, 'cancel'])->name('uploads.cancel');

        Route::get('/actividad', ActivityController::class)->name('activity');

        Route::get('/televisores', [DeviceController::class, 'index'])->name('devices.index');
        Route::get('/televisores/vincular', [DeviceController::class, 'pairForm'])->name('devices.pair');
        Route::post('/televisores/vincular', [DeviceController::class, 'pair'])->middleware('throttle:pair-claim')->name('devices.pair.store');
        Route::post('/televisores/este-navegador', [DeviceController::class, 'thisBrowser'])->name('devices.browser');
        Route::put('/televisores/{device}', [DeviceController::class, 'update'])->name('devices.update');
        Route::delete('/televisores/{device}', [DeviceController::class, 'destroy'])->name('devices.destroy');

        Route::get('/cuenta', [AccountController::class, 'show'])->name('account.show');
        Route::put('/cuenta', [AccountController::class, 'update'])->name('account.update');
        Route::delete('/cuenta/sesiones', [AccountController::class, 'revokeOtherSessions'])->name('account.sessions.revoke-others');
        Route::delete('/cuenta/sesiones/{session}', [AccountController::class, 'revokeSession'])->name('account.sessions.revoke');

        Route::middleware('confirmed')->group(function () {
            Route::get('/cuenta/pin', [AccountController::class, 'editPin'])->name('account.pin');
            Route::put('/cuenta/pin', [AccountController::class, 'updatePin'])->name('account.pin.update');
            Route::get('/cuenta/correo', [AccountController::class, 'editEmail'])->name('account.email');
            Route::post('/cuenta/correo', [AccountController::class, 'sendEmailChange'])->middleware('throttle:confirm-send')->name('account.email.send');
            Route::post('/cuenta/correo/confirmar', [AccountController::class, 'confirmEmailChange'])->middleware('throttle:login-verify')->name('account.email.confirm');
            Route::get('/cuenta/exportar', [AccountController::class, 'export'])->name('account.export');
            Route::get('/cuenta/eliminar', [AccountController::class, 'confirmDelete'])->name('account.delete');
            Route::delete('/cuenta', [AccountController::class, 'destroy'])->name('account.destroy');
        });
    });
});
