<?php

namespace App\Http\Controllers\Report;

use App\Events\NewReportSubmitted;
use App\Http\Controllers\Controller;
use App\Models\Report;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    /**
     * Submit a new report (public, optionally authenticated).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'target_type'      => 'required|in:user,center,group,supplier,zone,platform',
            'target_id'        => 'nullable|integer|min:1',
            'location_lat'     => 'nullable|numeric|between:-90,90',
            'location_lng'     => 'nullable|numeric|between:-180,180',
            'subject'          => 'required|string|max:255',
            'description'      => 'required|string|max:5000',
            'reported_user_id' => 'nullable|exists:users,id',
            'page_url'         => 'nullable|url|max:500',
            'screenshot'       => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120',
        ]);

        $screenshotPath = null;
        if ($request->hasFile('screenshot')) {
            $screenshotPath = $request->file('screenshot')->store('reports/screenshots', 'public');
        }

        // Auto-assign priority based on target type
        $priority = match ($validated['target_type']) {
            'zone'   => 'high',
            'user'   => 'medium',
            default  => 'low',
        };

        $report = Report::create([
            'reporter_user_id' => auth()->id(),
            'reported_user_id' => $validated['reported_user_id'] ?? null,
            'target_type'      => $validated['target_type'],
            'target_id'        => $validated['target_id'] ?? null,
            'location_lat'     => $validated['location_lat'] ?? null,
            'location_lng'     => $validated['location_lng'] ?? null,
            'report_type'      => $validated['target_type'],
            'subject'          => $validated['subject'],
            'description'      => $validated['description'],
            'page_url'         => $validated['page_url'] ?? null,
            'screenshot_path'  => $screenshotPath,
            'status'           => 'pending',
            'priority'         => $priority,
        ]);

        $report->load('reporter');
        broadcast(new NewReportSubmitted($report));

        return response()->json([
            'status'  => 'success',
            'message' => 'Report submitted successfully.',
        ], 201);
    }

    /**
     * Admin: list all reports with optional filters.
     */
    public function index(Request $request)
    {
        $query = Report::with([
            'reporter:id,first_name,last_name,email,avatar',
            'reportedUser:id,first_name,last_name,email,avatar',
        ])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('type')) {
            $query->where('target_type', $request->type);
        }
        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('subject', 'like', "%{$request->search}%")
                  ->orWhere('description', 'like', "%{$request->search}%");
            });
        }

        $reports = $query->paginate(20);

        return response()->json(['status' => 'success', 'data' => $reports]);
    }

    /**
     * Admin: update report status or add a note.
     */
    public function update(Request $request, $id)
    {
        $report    = Report::findOrFail($id);
        $validated = $request->validate([
            'status'     => 'sometimes|in:pending,reviewing,resolved',
            'admin_note' => 'sometimes|nullable|string|max:2000',
        ]);

        $report->update($validated);
        $report->load(['reporter:id,first_name,last_name,email,avatar', 'reportedUser:id,first_name,last_name,email,avatar']);

        return response()->json(['status' => 'success', 'data' => $report]);
    }

    /**
     * Admin: delete a report.
     */
    public function destroy($id)
    {
        $report = Report::findOrFail($id);

        if ($report->screenshot_path) {
            \Storage::disk('public')->delete($report->screenshot_path);
        }

        $report->delete();

        return response()->json(['status' => 'success', 'message' => 'Report deleted.']);
    }
}
