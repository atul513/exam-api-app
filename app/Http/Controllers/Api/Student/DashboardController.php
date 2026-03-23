<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'role'    => 'student',
            'user'    => $user,
            'data'    => [
                'welcome_message' => "Welcome, {$user->name}! Here is your student dashboard.",
            ],
        ]);
    }

    // Student views their own profile
    public function profile(Request $request)
    {
        return response()->json([
            'success' => true,
            'profile' => $request->user(),
        ]);
    }
}
