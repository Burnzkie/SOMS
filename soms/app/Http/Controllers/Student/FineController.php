<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class FineController extends Controller
{
    /**
     * Student's own fines, read-only. No dispute button, no online payment —
     * see 05-Attendance-Fines.md Part D, "Why fines are Treasurer-only."
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        $query = $user->fines()->with(['event', 'eventSession']);

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $fines = $query->orderByDesc('issued_at')->paginate(15)->withQueryString();

        return view('student.fines.index', [
            'fines' => $fines,
        ]);
    }
}
