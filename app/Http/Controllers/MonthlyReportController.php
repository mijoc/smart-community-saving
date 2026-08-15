<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Services\ActivityLogger;
use App\Services\MonthlyReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;

class MonthlyReportController extends Controller
{
    public function __construct(protected MonthlyReportService $reports) {}

    /**
     * Filter form + on-screen ledger view.
     *
     *  GET /reports/monthly?group_id=1&month=2026-01
     */
    public function index(Request $request)
    {
        $user      = auth()->user();
        $activeId  = (int) session('active_group_id');
        $groupOpts = $this->accessibleGroupOptions();

        // Non-super-admins only see the active group as an option.
        if (! $user->isSuperAdmin()) {
            $groupOpts = $activeId
                ? $groupOpts->where('id', $activeId)->values()
                : collect();
        }

        if ($groupOpts->isEmpty() && ! $user->isSuperAdmin()) {
            abort(403, 'No groups accessible.');
        }

        $groupId = (int) $request->input('group_id', $activeId);
        if (! $user->isSuperAdmin()) {
            $groupId = $activeId;
        }
        if (! $groupId && $groupOpts->isNotEmpty()) {
            $groupId = (int) $groupOpts->first()->id;
        }

        $month = $this->parseMonth($request->input('month'));

        $report = null;
        $group  = null;
        if ($groupId) {
            $group = Group::find($groupId);
            if ($group) {
                $this->authorizeGroupAccess($group);
                $report = $this->reports->generate($group, $month);
            }
        }

        return view('reports.monthly.index', [
            'group'        => $group,
            'groupOptions' => $groupOpts,
            'month'        => $month,
            'report'       => $report,
        ]);
    }

    /**
     * Printable view (clean layout, no chrome).
     */
    public function print(Request $request)
    {
        [$group, $month, $report] = $this->resolveReport($request);
        return view('reports.monthly.print', compact('group', 'month', 'report'));
    }

    /**
     * Export to PDF or Excel. Logs an audit-trail entry on success.
     */
    public function export(Request $request, string $format)
    {
        $format = strtolower($format);
        abort_unless(in_array($format, ['pdf', 'xlsx'], true), 400, 'Unsupported format');

        [$group, $month, $report] = $this->resolveReport($request);

        ActivityLogger::log(
            groupId:     $group->id,
            type:        'report.generated',
            description: "downloaded the {$month->format('F Y')} financial report ({$format})",
            icon:        'file-spreadsheet',
            color:       'blue',
            data:        ['report' => 'monthly', 'format' => $format, 'period' => $month->format('Y-m')],
        );

        if ($format === 'xlsx') {
            return $this->reports->exportXlsx($report);
        }

        $pdf = Pdf::loadView('reports.monthly._pdf', compact('group', 'month', 'report'))
            ->setPaper('a3', 'landscape');

        $name = 'monthly-report-'.\Illuminate\Support\Str::slug($group->name).'-'.$month->format('Y-m').'.pdf';
        return $pdf->download($name);
    }

    // ------------------------------------------------------------------
    // helpers
    // ------------------------------------------------------------------
    protected function resolveReport(Request $request): array
    {
        $groupId = (int) $request->input('group_id', session('active_group_id'));
        abort_if(! $groupId, 422, 'Group is required');
        $group = Group::findOrFail($groupId);
        $this->authorizeGroupAccess($group);
        $month = $this->parseMonth($request->input('month'));
        $report = $this->reports->generate($group, $month);
        return [$group, $month, $report];
    }

    protected function parseMonth(?string $raw): Carbon
    {
        if (! $raw) return now()->startOfMonth();
        try {
            return Carbon::createFromFormat('Y-m', $raw)->startOfMonth();
        } catch (\Throwable) {
            try { return Carbon::parse($raw)->startOfMonth(); }
            catch (\Throwable) { return now()->startOfMonth(); }
        }
    }

    protected function authorizeGroupAccess(Group $group): void
    {
        $user = auth()->user();
        if ($user->isSuperAdmin()) return;

        // Non-super-admins may only run a report for the group they are
        // currently switched into, even if they're assigned to others.
        $activeId = (int) session('active_group_id');
        abort_unless($activeId && $group->id === $activeId, 403, 'You do not have access to this group.');
    }
}
