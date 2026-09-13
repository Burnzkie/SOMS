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

        // Independent of whatever status filter is currently applied —
        // the "go pay" banner should only show if there's an actual
        // unpaid fine, not just because the Paid/Waived filter happens
        // to return rows.
        $hasUnpaidFine = $user->fines()->where('status', 'unpaid')->exists();

        return view('student.fines.index', [
            'fines' => $fines,
            'hasUnpaidFine' => $hasUnpaidFine,
        ]);
    }
}
