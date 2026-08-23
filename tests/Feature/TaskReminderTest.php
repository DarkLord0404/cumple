<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Organization;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskReminderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TaskReminderTest extends TestCase
{
    use RefreshDatabase;

    public function test_due_reminders_are_sent_once_to_every_responsible(): void
    {
        Notification::fake();
        $organization = Organization::create([
            'name' => 'Organización', 'slug' => 'organizacion', 'is_active' => true,
            'reminders_enabled' => true, 'reminder_days' => [7, 3, 1],
        ]);
        $creator = User::factory()->create(['organization_id' => $organization->id, 'role' => 'administrator']);
        $responsible = User::factory()->create(['organization_id' => $organization->id]);
        $area = Area::withoutGlobalScopes()->create([
            'organization_id' => $organization->id, 'name' => 'Operaciones', 'slug' => 'operaciones', 'is_active' => true,
        ]);
        $task = Task::withoutGlobalScopes()->forceCreate([
            'organization_id' => $organization->id, 'code' => 'REM-001', 'title' => 'Acción próxima',
            'area_id' => $area->id,
            'created_by' => $creator->id, 'assigned_to' => $responsible->id, 'assignee_type' => 'internal',
            'status' => 'in_progress', 'progress' => 20, 'due_at' => today()->addDays(3),
        ]);
        $task->assignees()->sync([$responsible->id]);

        Artisan::call('tasks:send-reminders');
        Artisan::call('tasks:send-reminders');

        Notification::assertSentToTimes($responsible, TaskReminderNotification::class, 1);
        $this->assertDatabaseCount('task_reminder_logs', 1);
    }

    public function test_review_alerts_reach_quality_and_medical_directorate_once(): void
    {
        Notification::fake();
        $organization = Organization::create([
            'name' => 'Organización', 'slug' => 'organizacion', 'is_active' => true,
            'reminders_enabled' => false, 'review_alerts_enabled' => true,
        ]);
        $creator = User::factory()->create(['organization_id' => $organization->id, 'role' => 'administrator']);
        $quality = User::factory()->create(['organization_id' => $organization->id, 'role' => 'quality']);
        $medical = User::factory()->create(['organization_id' => $organization->id, 'role' => 'coordinator_medical']);
        $area = Area::withoutGlobalScopes()->create([
            'organization_id' => $organization->id, 'name' => 'Dirección Médica',
            'slug' => 'direccion-medica', 'coordinator_id' => $medical->id, 'is_active' => true,
        ]);
        Task::withoutGlobalScopes()->forceCreate([
            'organization_id' => $organization->id, 'code' => 'REV-001', 'title' => 'Acción para revisar',
            'area_id' => $area->id,
            'created_by' => $creator->id, 'assignee_type' => 'internal', 'status' => 'in_review',
            'progress' => 100, 'submitted_at' => now(), 'due_at' => today()->addWeek(),
        ]);

        Artisan::call('tasks:send-reminders');
        Artisan::call('tasks:send-reminders');

        Notification::assertSentToTimes($quality, TaskReminderNotification::class, 1);
        Notification::assertSentToTimes($medical, TaskReminderNotification::class, 1);
        $this->assertDatabaseCount('task_reminder_logs', 2);
    }
}
