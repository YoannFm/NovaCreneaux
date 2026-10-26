<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('hello::index'))->name('index');
