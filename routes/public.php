<?php

use Illuminate\Support\Facades\Route;

// =============================================
// ZONA PÚBLICA: E-Commerce (sin autenticación)
// =============================================

// Catálogo Público
Route::livewire('catalogo', 'pages::ecommerce.catalogo')->name('catalogo');

// Ficha de Producto
Route::livewire('producto/{producto}/{slug}', 'pages::ecommerce.producto')->name('producto.detalle');

// Bolsa de Compras
Route::livewire('carrito', 'pages::ecommerce.carrito')->name('carrito');

// Checkout (requiere estar autenticado)
Route::livewire('checkout', 'pages::ecommerce.checkout')
    ->name('checkout')
    ->middleware(['auth', 'verified']);
