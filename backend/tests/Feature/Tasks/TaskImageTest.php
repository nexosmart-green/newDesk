<?php

use App\Domain\Identity\DefaultRoles;
use App\Domain\Identity\Enums\Permission;
use App\Domain\Tasks\Models\TaskImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/ParityHelpers.php';

beforeEach(fn () => Storage::fake(TaskImage::DISK));

it('stores a pasted image privately and serves it to whoever sees the project', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    ['project' => $project] = bagProject($admin, [$dev], 3600);

    $url = $this->actingAs($dev)->post("/api/v1/projects/{$project->id}/task-images", ['image' => UploadedFile::fake()->image('captura.png', 40, 20)], ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonPath('message', 'Imagen subida.')
        ->json('data.url');

    expect($url)->toMatch('#^/api/v1/task-images/[0-9a-f-]{36}$#');
    $image = TaskImage::query()->sole();
    expect($image->mime)->toBe('image/png')
        ->and($image->uploaded_by)->toBe($dev->id)
        ->and($image->path)->toEndWith('.png');
    Storage::disk(TaskImage::DISK)->assertExists($image->path);

    $response = $this->actingAs($admin)->get($url)->assertOk();
    expect($response->headers->get('Content-Type'))->toBe('image/png')
        ->and($response->headers->get('Cache-Control'))->toContain('max-age=86400')
        ->and($response->headers->get('Cache-Control'))->toContain('private')
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->streamedContent())->toBe(Storage::disk(TaskImage::DISK)->get($image->path));
});

it('asks to convert iPhone photos and rejects non images or files over 5 MB', function () {
    $admin = adminUser();
    ['project' => $project] = bagProject($admin, [], 3600);
    $upload = fn (UploadedFile $file) => $this->actingAs($admin)->post("/api/v1/projects/{$project->id}/task-images", ['image' => $file], ['Accept' => 'application/json']);
    $heic = "\x00\x00\x00\x18ftypheic\x00\x00\x00\x00mif1heic".str_repeat("\x00", 64);

    $upload(UploadedFile::fake()->createWithContent('IMG_0001.HEIC', $heic))
        ->assertUnprocessable()->assertJsonPath('message', 'Las fotos HEIC del iPhone no se pueden subir. Convertila a JPG o PNG.')->assertJsonPath('data.code', 'task_image_heic');
    $upload(UploadedFile::fake()->createWithContent('notas.png', 'esto no es una imagen'))
        ->assertUnprocessable()->assertJsonPath('data.code', 'task_image_format');
    $upload(UploadedFile::fake()->createWithContent('dibujo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'))
        ->assertUnprocessable()->assertJsonPath('data.code', 'task_image_format');
    $upload(UploadedFile::fake()->image('enorme.jpg')->size(6000))
        ->assertUnprocessable()->assertJsonPath('errors.image.0', 'La imagen puede pesar hasta 5 MB.');

    expect(TaskImage::query()->count())->toBe(0);
});

it('hides images and uploads from people outside the project or company (404) and honors the projects module', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    $outsider = userWithRole($admin);
    ['project' => $project] = bagProject($admin, [$dev], 3600);
    $url = $this->actingAs($dev)->post("/api/v1/projects/{$project->id}/task-images", ['image' => UploadedFile::fake()->image('a.jpg')], ['Accept' => 'application/json'])
        ->assertCreated()->json('data.url');

    $this->actingAs($outsider)->getJson($url)->assertNotFound();
    $this->actingAs($outsider)->post("/api/v1/projects/{$project->id}/task-images", ['image' => UploadedFile::fake()->image('b.jpg')], ['Accept' => 'application/json'])
        ->assertNotFound();
    $this->actingAs(adminUser('otra@example.com'))->getJson($url)->assertNotFound();
    $this->actingAs($dev)->getJson('/api/v1/task-images/00000000-0000-0000-0000-000000000000')->assertNotFound();

    revokeModule($admin, DefaultRoles::COLLABORATOR, Permission::ModuleProjects);
    $this->actingAs($dev->fresh())->getJson($url)->assertForbidden();
});

it('moves the description images with the task so the destination members still see them', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    ['project' => $from, 'task' => $task] = bagProject($admin, [], 3600, 'aaa');
    ['project' => $to] = bagProject($admin, [$dev], 3600, 'bbb');
    $url = $this->actingAs($admin)->post("/api/v1/projects/{$from->id}/task-images", ['image' => UploadedFile::fake()->image('a.png')], ['Accept' => 'application/json'])->json('data.url');
    $task->forceFill(['description' => "Mirá esto:\n\n![captura]({$url})"])->save();

    $this->actingAs($dev)->get($url)->assertNotFound();
    $this->actingAs($admin)->putJson("/api/v1/tasks/{$task->code}/project", ['project_id' => $to->id])->assertOk();

    $this->actingAs($dev)->get($url)->assertOk();
});

it('moves the images pasted in comments with the task too, and markdown comments keep their mentions', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    $teammate = userWithRole($admin);
    ['project' => $from, 'task' => $task] = bagProject($admin, [$dev, $teammate], 3600, 'aaa');
    ['project' => $to] = bagProject($admin, [$dev], 3600, 'bbb');
    $upload = fn () => $this->actingAs($dev)->post("/api/v1/projects/{$from->id}/task-images", ['image' => UploadedFile::fake()->image('c.png')], ['Accept' => 'application/json'])
        ->assertCreated()->json('data.url');
    $inComment = $upload();
    $unrelated = $upload();

    $this->actingAs($dev)->postJson("/api/v1/tasks/{$task->code}/comments", [
        'body' => "**Listo** el fix, @Equipo:\n\n- paso 1\n- paso 2\n\n![captura]({$inComment})",
        'mentioned_user_ids' => [$teammate->id],
    ])->assertCreated()
        ->assertJsonPath('data.body', "**Listo** el fix, @Equipo:\n\n- paso 1\n- paso 2\n\n![captura]({$inComment})")
        ->assertJsonPath('data.mentions.0.id', $teammate->id);

    $this->actingAs($admin)->putJson("/api/v1/tasks/{$task->code}/project", ['project_id' => $to->id])->assertOk();

    expect(TaskImage::query()->withoutGlobalScopes()->where('uuid', basename($inComment))->value('project_id'))->toBe($to->id)
        ->and(TaskImage::query()->withoutGlobalScopes()->where('uuid', basename($unrelated))->value('project_id'))->toBe($from->id);
});
