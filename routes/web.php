<?php

use Illuminate\Support\Facades\Route;

// Página de inicio
Route::view('/', 'welcome')->name('home');

// =============================================
// DESPACHADOR DE DASHBOARD SEGÚN ROL
// =============================================
Route::get('dashboard', function () {
    if (auth()->check()) {
        $user = auth()->user();

        if ($user->hasPermissionTo('dashboard.ver')) {
            return redirect()->route('admin.dashboard');
        }

        if ($user->hasPermissionTo('ventas.ver')) {
            return redirect()->route('admin.ventas.index');
        }

        if ($user->hasPermissionTo('guias.ver')) {
            return redirect()->route('admin.guias.index');
        }

        if ($user->hasPermissionTo('productos.ver')) {
            return redirect()->route('admin.productos.index');
        }

        if ($user->hasPermissionTo('panel.acceder')) {
            return redirect()->route('admin.dashboard'); // Fallback in case of weird permissions
        }

        return redirect()->route('home');
    }

    return redirect()->route('login');
})->middleware(['auth', 'verified'])->name('dashboard');

require __DIR__.'/settings.php';
