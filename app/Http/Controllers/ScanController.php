<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ScannerController extends Controller
{
    public function scan(Request $request)
    {
        return response()->json([
            'success' => true,
            'message' => 'Controller is working'
        ]);
    }
}