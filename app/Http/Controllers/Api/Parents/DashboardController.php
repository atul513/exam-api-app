<?php

namespace App\Http\Controllers\Api\Parents;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'role'    => 'parent',
            'user'    => $user,
            'data'    => [
                'welcome_message' => "Welcome, {$user->name}! Here is your parent dashboard.",
            ],
        ]);
    }

    // Parent views their own profile
    public function profile(Request $request)
    {
        return response()->json([
            'success' => true,
            'profile' => $request->user(),
        ]);
    }
}
