<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class CustomerProfileController extends Controller
{
    public function show(Request $request)
    {
        $customer = $request->user();
        return response()->json($customer->load('person'));
    }
}
