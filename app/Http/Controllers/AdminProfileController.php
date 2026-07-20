<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;

class AdminProfileController extends Controller
{
    public function show(Request $request)
    {
        $admin = $request->user();
        return response()->json($admin->load('person'));
    }
}
