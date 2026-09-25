<?php
use Illuminate\Support\Facades\Route;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

Route::get('/test-primer-login', function () {
     = User::find(2); // assuming profesor is ID 2
    auth()->login();
    return 'Logged in as ' . ->email . ' with primer_login=' . ->primer_login;
});
