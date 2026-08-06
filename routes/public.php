<?php

use Illuminate\Support\Facades\Route;

// =============================================
// ZONA PÚBLICA: E-Commerce (sin autenticación)
// =============================================

// Catálogo Público
Route::livewire('catalogo', 'pages::catalogo')->name('catalogo');

// Ficha de Producto
Route::livewire('producto/{producto}/{slug}', 'pages::producto')->name('producto.detalle');

// Bolsa de Compras
Route::livewire('carrito', 'pages::carrito')->name('carrito');

// Checkout (requiere estar autenticado)
Route::livewire('checkout', 'pages::checkout')
    ->name('checkout')
    ->middleware(['auth', 'verified']);
