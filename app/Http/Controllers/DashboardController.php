<?php

namespace App\Http\Controllers;

use App\Models\ImprovementCase;
use App\Models\Task;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $tasks = Task::query()->with(['improvementCase', 'area', 'assignee']);
        $this->applyVisibility($tasks, $request);
        $open = (clone $tasks)->whereNotIn('status', ['completed', 'cancelled']);

        return view('dashboard', [
            'metrics' => [
                'pending' => (clone $tasks)->where('status', 'pending')->count(),
                'in_progress' => (clone $tasks)->where('status', 'in_progress')->count(),
                'in_review' => (clone $tasks)->where('status', 'in_review')->count(),
                'overdue' => (clone $open)->where('due_at', '<', now())->count(),
            ],
            'upcomingTasks' => (clone $open)->orderByRaw('case when due_at is null then 1 else 0 end')->orderBy('due_at')->limit(8)->get(),
            'openCases' => ImprovementCase::whereNotIn('status', ['closed', 'cancelled'])->count(),
        ]);
    }

    private function applyVisibility(Builder $query, Request $request): void
    {
        $user = $request->user();
        if (in_array($user->role, ['administrator', 'quality'])) {
            return;
        }
        $query->where(function (Builder $query) use ($user) {
            $query->where('assigned_to', $user->id);
            if ($user->role === 'coordinator' && $user->area_id) {
                $query->orWhere('area_id', $user->area_id);
            }
        });
    }
}
