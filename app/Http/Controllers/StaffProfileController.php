<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class StaffProfileController extends Controller
{
    public function show(Request $request)
    {
        $staff = $request->user();

        return response()->json($staff->load(['person', 'managingAdmin.person']));
    }
}
