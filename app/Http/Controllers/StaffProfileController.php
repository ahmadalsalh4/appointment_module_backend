<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;

class StaffProfileController extends Controller
{
    public function show(Request $request)
    {
        $staff = $request->user();
        return response()->json($staff->load('person'));
    }
}
