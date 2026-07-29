<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * @deprecated Use {@see LogoutController} instead. Kept for backward
 * compatibility with the existing route definition
 * (`POST /staff/logout`).
 */
class StaffAuthController extends LogoutController
{
    public function logout(Request $request)
    {
        return parent::logout($request);
    }
}