<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

// Página de inicio
Route::view('/', 'welcome')->name('home');

// =============================================
// DESPACHADOR DE DASHBOARD SEGÚN ROL
// =============================================
Route::get('dashboard', function () {
    if (auth()->check()) {
        $user = auth()->user();

        if ($user->can('dashboard.ver')) {
            return redirect()->route('admin.dashboard');
        }

        if ($user->can('ventas.ver')) {
            return redirect()->route('admin.ventas.index');
        }

        if ($user->can('guias.ver')) {
            return redirect()->route('admin.guias.index');
        }

        if ($user->can('productos.ver')) {
            return redirect()->route('admin.productos.index');
        }

        if ($user->can('panel.acceder')) {
            return redirect()->route('admin.dashboard'); // Fallback in case of weird permissions
        }

        return redirect()->route('home');
    }

    return redirect()->route('login');
})->middleware(['auth', 'verified'])->name('dashboard');

require __DIR__ . '/settings.php';

// Crear Storage Link
Route::get('/symlink', function () {
    Artisan::call('storage:link');
});

// Limpiar y resetear todo el caché
Route::get('/clear-cache', function () {
    Artisan::call('config:clear');
    Artisan::call('cache:clear');
    Artisan::call('route:clear');
    Artisan::call('view:clear');
    Artisan::call('storage:link');

    return '¡Caché limpiado y Storage Link creado con éxito!';
});
// Opcional: Optimizar una vez que ya funcione todo
Route::get('/optimize-app', function () {
    Artisan::call('optimize');

    return '¡Aplicación optimizada con éxito!';
});

// Ejecutar migrate:fresh --seed (Elimina todas las tablas y las crea desde cero con seeders)
Route::get('/run-migrations', function () {
    try {
        Artisan::call('migrate:fresh', [
            '--seed' => true,
            '--force' => true,
        ]);
        $output = Artisan::output();

        return '<pre>' . $output . '</pre><br>¡Base de datos reseteada y creada desde cero con seeds exitosamente!';
    } catch (Throwable $e) {
        return '<pre>' . $e->getMessage() . "\n" . $e->getTraceAsString() . '</pre>';
    }
});
