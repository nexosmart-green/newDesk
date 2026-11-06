<?php

use App\Domain\Identity\DefaultRoles;
use App\Domain\Notifications\Notifications\ExtensionRequestedNotification;
use App\Domain\Notifications\Notifications\HoursThresholdNotification;
use App\Domain\Notifications\Notifications\MentionedNotification;
use App\Domain\Notifications\Notifications\TaskAssignedNotification;
use App\Domain\Notifications\Notifications\TasksDueNotification;
use App\Domain\Notifications\Notifications\TimerCutNotification;
use App\Domain\Tasks\Actions\CreateTask;
use Illuminate\Support\Facades\Notification;

require_once __DIR__.'/../TimeTracking/TimeTrackingHelpers.php';

it('notifies the new assignee in the app and by e-mail (RF-09.01)', function () {
    Notification::fake();
    $admin = adminUser();
    $dev = userWithRole($admin);
    ['project' => $project] = bagProject($admin, [$dev], 3600);

    $this->actingAs($admin)->postJson("/api/v1/projects/{$project->id}/tasks", ['title' => 'Logo', 'assignee_ids' => [$dev->id]]);

    Notification::assertSentTo($dev, TaskAssignedNotification::class, fn ($n, $channels) => $channels === ['database', 'mail']);
});

it('notifies mentions (RF-09.02)', function () {
    Notification::fake();
    $admin = adminUser();
    $dev = userWithRole($admin);
    $designer = userWithRole($admin);
    ['task' => $task] = bagProject($admin, [$dev, $designer], 3600);

    $this->actingAs($dev)->postJson("/api/v1/tasks/{$task->code}/comments", ['body' => '¿Lo ves?', 'mentioned_user_ids' => [$designer->id]]);

    Notification::assertSentTo($designer, MentionedNotification::class);
});

it('warns Admin and leader at 80% / 100% and the person + Admin on budget cuts (RF-09.04/05)', function () {
    Notification::fake();
    $admin = adminUser();
    $dev = userWithRole($admin);
    ['task' => $task] = bagProject($admin, [$dev], 600);

    startTimer($dev, $task);
    $this->travel(11)->minutes();
    $this->artisan('timers:cut');

    Notification::assertSentTo($admin, HoursThresholdNotification::class);
    Notification::assertSentTo($dev, TimerCutNotification::class);
    Notification::assertSentTo($admin, TimerCutNotification::class);
});

it('tells hours.allocate holders about extension requests (RF-09.06)', function () {
    Notification::fake();
    $admin = adminUser();
    $manager = userWithRole($admin, DefaultRoles::MANAGER);
    $dev = userWithRole($admin);
    ['project' => $project] = bagProject($admin, [$dev], 0);

    $this->actingAs($dev)->postJson("/api/v1/projects/{$project->id}/extension-requests", ['message' => 'Falta poco']);

    Notification::assertSentTo([$admin, $manager], ExtensionRequestedNotification::class);
    Notification::assertNotSentTo($dev, ExtensionRequestedNotification::class);
});

it('sends one daily summary per person, once per day (RF-09.03)', function () {
    Notification::fake();
    $admin = adminUser();
    $dev = userWithRole($admin);
    ['project' => $project] = bagProject($admin, [$dev], 3600);
    app(CreateTask::class)($admin, $project, ['title' => 'Mañana', 'due_on' => today('America/Argentina/Buenos_Aires')->addDay()->toDateString()], [$dev->id]);
    app(CreateTask::class)($admin, $project, ['title' => 'Vencida', 'due_on' => today('America/Argentina/Buenos_Aires')->subDays(3)->toDateString()], [$dev->id]);

    $this->artisan('tasks:notify-due --force')->assertSuccessful();
    $this->artisan('tasks:notify-due --force')->assertSuccessful();

    Notification::assertSentToTimes($dev, TasksDueNotification::class, 1);
});

it('lists, counts and marks notifications as read in the bell', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    ['project' => $project] = bagProject($admin, [$dev], 3600);
    $this->actingAs($admin)->postJson("/api/v1/projects/{$project->id}/tasks", ['title' => 'Logo', 'assignee_ids' => [$dev->id]]);

    $items = $this->actingAs($dev)->getJson('/api/v1/notifications')
        ->assertOk()->assertJsonPath('meta.unread_count', 2) // task from bagProject + this one
        ->json('data');
    $id = collect($items)->firstWhere('title', 'Te asignaron bhn-2')['id'];

    $this->actingAs($dev)->postJson("/api/v1/notifications/{$id}/read")->assertJsonPath('data.unread_count', 1);
    $this->actingAs($dev)->postJson('/api/v1/notifications/read-all')->assertJsonPath('data.unread_count', 0);
});

it('respects e-mail preferences but never mutes hour-block e-mails for Admins', function () {
    Notification::fake();
    $admin = adminUser();
    $dev = userWithRole($admin);

    $this->actingAs($dev)->putJson('/api/v1/me/notification-preferences', ['preferences' => [['event' => 'task_assigned', 'email' => false]]])
        ->assertOk()->assertJsonFragment(['event' => 'task_assigned', 'email' => false]);
    $this->actingAs($admin)->putJson('/api/v1/me/notification-preferences', ['preferences' => [['event' => 'hours_threshold', 'email' => false]]])
        ->assertOk()->assertJsonFragment(['event' => 'hours_threshold', 'email' => true, 'locked' => true]);

    ['project' => $project] = bagProject($admin, [$dev], 3600);
    $this->actingAs($admin)->postJson("/api/v1/projects/{$project->id}/tasks", ['title' => 'X', 'assignee_ids' => [$dev->id]]);
    Notification::assertSentTo($dev, TaskAssignedNotification::class, fn ($n, $channels) => $channels === ['database']);
});
