<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\ImprovementCase;
use App\Models\Task;
use App\Models\User;
use App\Services\ApprovalWorkflow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, ApprovalWorkflow $approvalWorkflow): View
    {
        $canApprove = $approvalWorkflow->typeFor($request->user()) !== null;
        $tasks = Task::query()->with(['improvementCase', 'area', 'assignee', 'assignees']);
        $this->applyVisibility($tasks, $request);
        $availableTasks = clone $tasks;
        $availableTaskIds = (clone $availableTasks)->pluck('tasks.id');
        $assigneeIds = (clone $availableTasks)->whereNotNull('assigned_to')->pluck('assigned_to')
            ->merge(DB::table('task_user')->whereIn('task_id', $availableTaskIds)->pluck('user_id'))->unique();
        $open = (clone $tasks)->whereNotIn('status', ['completed', 'cancelled']);
        $filtered = clone $tasks;
        $this->applyFilters($filtered, $request);

        return view('dashboard', [
            'metrics' => [
                'pending' => (clone $tasks)->where('status', 'pending')->count(),
                'in_progress' => (clone $tasks)->where('status', 'in_progress')->count(),
                'in_review' => (clone $tasks)->where('status', 'in_review')->count(),
                'overdue' => (clone $open)->where('due_at', '<', now())->count(),
            ],
            'taskResults' => $filtered->orderByRaw("case when status = 'completed' then 1 else 0 end")
                ->orderByRaw('case when due_at is null then 1 else 0 end')->orderBy('due_at')->paginate(20)->withQueryString(),
            'openCases' => ImprovementCase::query()
                ->visibleTo($request->user(), $canApprove)
                ->whereNotIn('status', ['closed', 'cancelled'])->count(),
            'areas' => Area::whereIn('id', (clone $availableTasks)->whereNotNull('area_id')->distinct()->pluck('area_id'))->orderBy('name')->get(),
            'users' => User::whereIn('id', $assigneeIds)->orderBy('name')->get(),
            'taskStatuses' => (clone $availableTasks)->distinct()->pluck('status')->filter()->values(),
            'dueOptions' => collect([
                'overdue' => (clone $availableTasks)->whereNotIn('status', ['completed', 'cancelled'])->where('due_at', '<', now())->exists(),
                'next_7_days' => (clone $availableTasks)->whereNotIn('status', ['completed', 'cancelled'])->whereBetween('due_at', [now(), now()->addDays(7)])->exists(),
                'no_date' => (clone $availableTasks)->whereNull('due_at')->exists(),
            ])->filter()->keys(),
            'reviewTasks' => $canApprove
                ? Task::with(['improvementCase', 'assignees'])->where('status', 'in_review')->oldest('submitted_at')->get()
                : collect(),
        ]);
    }

    private function applyVisibility(Builder $query, Request $request): void
    {
        $user = $request->user();

        if ($user->role === 'administrator') {
            return;
        }

        $query->where(function (Builder $query) use ($user) {
            $query->where('assigned_to', $user->id)
                ->orWhereHas('assignees', fn ($assignees) => $assignees->whereKey($user->id));
        });
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        $query->when($request->filled('q'), function (Builder $query) use ($request): void {
            $search = '%'.$request->string('q')->trim().'%';
            $query->where(function (Builder $query) use ($search): void {
                $query->whereLike('title', $search, caseSensitive: false)
                    ->orWhereLike('description', $search, caseSensitive: false)
                    ->orWhereHas('improvementCase', fn (Builder $case) => $case
                        ->whereLike('code', $search, caseSensitive: false)->orWhereLike('title', $search, caseSensitive: false));
            });
        });
        $query->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')));
        $query->when($request->filled('area_id'), fn (Builder $query) => $query->where('area_id', $request->integer('area_id')));
        $query->when($request->filled('assignee_id'), fn (Builder $query) => $query->where(function (Builder $query) use ($request): void {
            $query->where('assigned_to', $request->integer('assignee_id'))
                ->orWhereHas('assignees', fn (Builder $users) => $users->whereKey($request->integer('assignee_id')));
        }));
        $query->when($request->string('due')->toString() === 'overdue', fn (Builder $query) => $query
            ->whereNotIn('status', ['completed', 'cancelled'])->where('due_at', '<', now()));
        $query->when($request->string('due')->toString() === 'next_7_days', fn (Builder $query) => $query
            ->whereNotIn('status', ['completed', 'cancelled'])->whereBetween('due_at', [now(), now()->addDays(7)]));
        $query->when($request->string('due')->toString() === 'no_date', fn (Builder $query) => $query->whereNull('due_at'));
    }
}
