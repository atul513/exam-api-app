<?php

namespace App\Http\Controllers\Api\Teacher;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        return response()->json([
            'success'  => true,
            'role'     => 'teacher',
            'user'     => $request->user(),
            'stats'    => [
                'total_students' => User::where('role', 'student')->count(),
            ],
        ]);
    }

    // Teachers can view their students
    public function students()
    {
        $students = User::where('role', 'student')->get();

        return response()->json([
            'success'  => true,
            'students' => $students,
        ]);
    }
}
