<?php

use Illuminate\Support\Facades\Route;

// Página única da aplicação. Toda a interação acontece via JavaScript + API REST.
Route::view('/', 'patients')->name('home');
