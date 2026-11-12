<?php

use App\Domain\Identity\DefaultRoles;
use App\Domain\Identity\Enums\Permission;
use App\Domain\Shared\Activity\ActivityLog;
use App\Domain\Tasks\Actions\CreateTask;
use App\Domain\Tasks\Models\Task;
use App\Domain\Tasks\Models\TaskAttachment;
use App\Domain\Tasks\Models\TaskChecklistItem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

require_once __DIR__.'/ParityHelpers.php';

beforeEach(fn () => Storage::fake(TaskAttachment::DISK));

function attach(Task $task, UploadedFile $file): TestResponse
{
    return test()->post("/api/v1/tasks/{$task->code}/attachments", ['file' => $file], ['Accept' => 'application/json']);
}

function pdfFile(string $name = 'presupuesto.pdf'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");
}

/** A zip with the entries Office/LibreOffice put in their files. */
function officeFile(string $name, array $entries): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'office');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::OVERWRITE);
    foreach ($entries as $entry => $content) {
        $zip->addFromString($entry, $content);
    }
    $zip->close();

    return UploadedFile::fake()->createWithContent($name, (string) file_get_contents($path));
}

it('attaches a file privately and lists it newest first with who uploaded it (RF-04.09)', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    ['task' => $task] = bagProject($admin, [$dev], 3600);
    $this->actingAs($dev);

    attach($task, UploadedFile::fake()->image('captura.png', 40, 20))
        ->assertCreated()
        ->assertJsonPath('message', 'Adjuntaste captura.png.')
        ->assertJsonPath('data.name', 'captura.png')
        ->assertJsonPath('data.mime', 'image/png')
        ->assertJsonPath('data.is_image', true)
        ->assertJsonPath('data.uploaded_by', ['id' => $dev->id, 'full_name' => $dev->fullName()])
        ->assertJsonPath('data.can_delete', true);
    attach($task, pdfFile())->assertCreated();

    $attachment = TaskAttachment::query()->where('mime', 'image/png')->sole();
    Storage::disk(TaskAttachment::DISK)->assertExists($attachment->path);
    expect($attachment->path)->toStartWith("task-attachments/{$task->company_id}/{$task->id}/")->toEndWith('.png');

    $this->getJson("/api/v1/tasks/{$task->code}/attachments")
        ->assertOk()
        ->assertJsonPath('data.*.name', ['presupuesto.pdf', 'captura.png'])
        ->assertJsonPath('data.1.uuid', $attachment->uuid)
        ->assertJsonPath('data.1.url', "/api/v1/task-attachments/{$attachment->uuid}")
        ->assertJsonPath('data.1.size', $attachment->size)
        ->assertJsonPath('data.0.is_image', false)
        ->assertJsonStructure(['data' => [['id', 'uuid', 'name', 'mime', 'size', 'url', 'is_image', 'uploaded_by', 'created_at', 'can_delete']]]);
    $this->getJson("/api/v1/tasks/{$task->code}")->assertJsonPath('data.attachments_count', 2);

    expect(ActivityLog::query()->where('subject_id', $task->id)->where('event', 'attachment.added')->pluck('after')->all())
        ->toBe([['name' => 'captura.png'], ['name' => 'presupuesto.pdf']]);
});

it('accepts documents by their contents and names them by what they really are', function () {
    $admin = adminUser();
    ['task' => $task] = bagProject($admin, [], 3600);
    $this->actingAs($admin);

    $cases = [
        ['Informe.PDF', pdfFile('Informe.PDF'), 'application/pdf', 'Informe.pdf'],
        ['contrato.docx', officeFile('contrato.docx', ['[Content_Types].xml' => '<Types/>', 'word/document.xml' => '<w/>']), 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'contrato.docx'],
        ['horas.xlsx', officeFile('horas.xlsx', ['[Content_Types].xml' => '<Types/>', 'xl/workbook.xml' => '<w/>']), 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'horas.xlsx'],
        ['notas.odt', officeFile('notas.odt', ['mimetype' => 'application/vnd.oasis.opendocument.text', 'content.xml' => '<a/>']), 'application/vnd.oasis.opendocument.text', 'notas.odt'],
        ['fuentes.zip', officeFile('fuentes.zip', ['a.txt' => 'hola']), 'application/zip', 'fuentes.zip'],
        ['clientes.csv', UploadedFile::fake()->createWithContent('clientes.csv', "Nombre;Día\nJosé;lunes\n"), 'text/csv', 'clientes.csv'],
        ['leeme.txt', UploadedFile::fake()->createWithContent('leeme.txt', "Notas de la reunión\n"), 'text/plain', 'leeme.txt'],
    ];

    foreach ($cases as [$label, $file, $mime, $name]) {
        attach($task, $file)->assertCreated()->assertJsonPath('data.mime', $mime)->assertJsonPath('data.name', $name);
    }
    expect(TaskAttachment::query()->count())->toBe(count($cases));
});

it('rejects disguised, unsupported and iPhone files with clear messages', function () {
    $admin = adminUser();
    ['task' => $task] = bagProject($admin, [], 3600);
    $this->actingAs($admin);
    $heic = "\x00\x00\x00\x18ftypheic\x00\x00\x00\x00mif1heic".str_repeat("\x00", 64);

    attach($task, UploadedFile::fake()->createWithContent('IMG_0001.HEIC', $heic))
        ->assertUnprocessable()->assertJsonPath('message', 'Convertí la imagen a JPG o PNG.');

    $refused = [
        UploadedFile::fake()->createWithContent('factura.pdf', '<!DOCTYPE html><html><body><script>fetch("/api/v1/me")</script></body></html>'),
        UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
        UploadedFile::fake()->createWithContent('setup.exe', "MZ\x90\x00\x03\x00\x00\x00\x04\x00\x00\x00\xff\xff".str_repeat("\x00", 64)),
        UploadedFile::fake()->createWithContent('raro.bin', "\x00\x01\x02binario\xff\xfe"),
    ];
    foreach ($refused as $file) {
        attach($task, $file)->assertUnprocessable()
            ->assertJsonPath('message', 'Ese tipo de archivo no se puede adjuntar.')
            ->assertJsonPath('data.code', 'attachment_type');
    }

    attach($task, UploadedFile::fake()->create('enorme.pdf', 20 * 1024 + 1))
        ->assertUnprocessable()->assertJsonPath('errors.file.0', 'El archivo puede pesar hasta 20 MB.');
    $this->post("/api/v1/tasks/{$task->code}/attachments", [], ['Accept' => 'application/json'])
        ->assertUnprocessable()->assertJsonPath('errors.file.0', 'Elegí un archivo para subir.');

    expect(TaskAttachment::query()->count())->toBe(0)
        ->and(Storage::disk(TaskAttachment::DISK)->allFiles())->toBe([]);
});

it('allows at most 50 attachments per task and leaves no orphan file behind', function () {
    $admin = adminUser();
    ['task' => $task] = bagProject($admin, [], 3600);
    TaskAttachment::query()->insert(array_map(fn (int $n) => [
        'uuid' => (string) Str::uuid(), 'company_id' => $task->company_id, 'task_id' => $task->id, 'uploaded_by' => $admin->id,
        'original_name' => "archivo-{$n}.txt", 'path' => "task-attachments/x/{$n}.txt", 'mime' => 'text/plain', 'size' => 1, 'created_at' => now(),
    ], range(1, 50)));

    $this->actingAs($admin);
    attach($task, pdfFile())
        ->assertUnprocessable()
        ->assertJsonPath('data.code', 'attachment_limit')
        ->assertJsonPath('message', 'La tarea ya tiene 50 archivos, que es el máximo. Borrá alguno antes de subir otro.');

    expect(TaskAttachment::query()->count())->toBe(50)
        ->and(Storage::disk(TaskAttachment::DISK)->allFiles())->toBe([]);
});

it('serves images and PDFs inline and the rest as downloads, with the original unicode name and safe headers', function () {
    $admin = adminUser();
    ['task' => $task] = bagProject($admin, [], 3600);
    $this->actingAs($admin);
    $pdfUrl = attach($task, pdfFile('Presupuesto año 2026 – v2.pdf'))->json('data.url');
    $pngUrl = attach($task, UploadedFile::fake()->image('foto.png'))->json('data.url');
    $csvUrl = attach($task, UploadedFile::fake()->createWithContent('horas de José.csv', "a;b\n1;2\n"))->json('data.url');

    $pdf = $this->get($pdfUrl)->assertOk();
    expect($pdf->headers->get('Content-Type'))->toBe('application/pdf')
        ->and($pdf->headers->get('Content-Disposition'))->toStartWith('inline;')
        ->and($pdf->headers->get('Content-Disposition'))->toContain("filename*=utf-8''Presupuesto%20a%C3%B1o%202026%20%E2%80%93%20v2.pdf")
        ->and($pdf->headers->get('Content-Disposition'))->toContain('filename="Presupuesto ano 2026 - v2.pdf"')
        ->and($pdf->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        // Chrome's PDF viewer refuses to open under a sandbox CSP; the content was sniffed on upload instead.
        ->and($pdf->headers->has('Content-Security-Policy'))->toBeFalse()
        ->and($pdf->headers->get('Cache-Control'))->toContain('private')
        ->and($pdf->streamedContent())->toStartWith('%PDF-1.4');

    expect($this->get($pngUrl)->assertOk()->headers->get('Content-Disposition'))->toStartWith('inline;');

    $csv = $this->get($csvUrl)->assertOk();
    expect($csv->headers->get('Content-Disposition'))->toStartWith('attachment;')
        ->and($csv->headers->get('Content-Disposition'))->toContain("filename*=utf-8''horas%20de%20Jos%C3%A9.csv")
        ->and($csv->headers->get('Content-Type'))->toStartWith('text/csv')
        ->and($csv->headers->get('Content-Security-Policy'))->toBe("sandbox; default-src 'none'");
    expect($this->get($pngUrl)->headers->get('Content-Security-Policy'))->toBe("sandbox; default-src 'none'");
});

it('lets the uploader and projects.manage delete an attachment, not other members', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    $teammate = userWithRole($admin);
    $manager = userWithRole($admin, DefaultRoles::MANAGER);
    ['task' => $task] = bagProject($admin, [$dev, $teammate, $manager], 3600);
    $this->actingAs($dev);
    $first = TaskAttachment::query()->findOrFail(attach($task, pdfFile('uno.pdf'))->json('data.id'));
    $second = TaskAttachment::query()->findOrFail(attach($task, pdfFile('dos.pdf'))->json('data.id'));

    $this->actingAs($teammate)->getJson("/api/v1/tasks/{$task->code}/attachments")->assertJsonPath('data.*.can_delete', [false, false]);
    $this->actingAs($manager)->getJson("/api/v1/tasks/{$task->code}/attachments")->assertJsonPath('data.*.can_delete', [true, true]);

    $this->actingAs($teammate)->deleteJson("/api/v1/task-attachments/{$first->uuid}")
        ->assertForbidden()->assertJsonPath('message', 'Solo quien subió el archivo o quien gestiona proyectos puede borrarlo.');

    $this->actingAs($dev)->deleteJson("/api/v1/task-attachments/{$first->uuid}")->assertOk()->assertJsonPath('message', 'Borraste uno.pdf.');
    $this->actingAs($manager)->deleteJson("/api/v1/task-attachments/{$second->uuid}")->assertOk();

    expect(TaskAttachment::query()->count())->toBe(0)
        ->and(Storage::disk(TaskAttachment::DISK)->allFiles())->toBe([])
        ->and(ActivityLog::query()->where('event', 'attachment.removed')->pluck('before')->all())->toBe([['name' => 'uno.pdf'], ['name' => 'dos.pdf']]);
    $this->actingAs($dev)->get($first->url())->assertNotFound();
});

it('hides attachments from people outside the project or company (404) and honors the projects module', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    $outsider = userWithRole($admin);
    ['task' => $task] = bagProject($admin, [$dev], 3600);
    $this->actingAs($dev);
    $attachment = TaskAttachment::query()->findOrFail(attach($task, pdfFile())->json('data.id'));

    foreach ([$outsider, adminUser('otra@example.com')] as $stranger) {
        $this->actingAs($stranger);
        $this->getJson("/api/v1/tasks/{$task->id}/attachments")->assertNotFound();
        $this->post("/api/v1/tasks/{$task->id}/attachments", ['file' => pdfFile()], ['Accept' => 'application/json'])->assertNotFound();
        $this->get($attachment->url(), ['Accept' => 'application/json'])->assertNotFound();
        $this->deleteJson($attachment->url())->assertNotFound();
    }
    $this->actingAs($dev)->getJson('/api/v1/task-attachments/00000000-0000-0000-0000-000000000000')->assertNotFound();
    expect(TaskAttachment::query()->withoutGlobalScopes()->count())->toBe(1);

    revokeModule($admin, DefaultRoles::COLLABORATOR, Permission::ModuleProjects);
    $this->actingAs($dev->fresh())->getJson($attachment->url())->assertForbidden()->assertJsonPath('data.code', 'module_disabled');
});

it('keeps the attachments with the task when it moves to another project', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    ['task' => $task] = bagProject($admin, [], 3600, 'aaa');
    ['project' => $to] = bagProject($admin, [$dev], 3600, 'bbb');
    $this->actingAs($admin);
    $url = attach($task, pdfFile())->json('data.url');

    $this->actingAs($dev)->get($url)->assertNotFound();
    $this->actingAs($admin)->putJson("/api/v1/tasks/{$task->code}/project", ['project_id' => $to->id])->assertOk();

    $this->actingAs($dev)->get($url)->assertOk();
    $this->actingAs($dev)->getJson("/api/v1/tasks/{$task->id}/attachments")->assertJsonCount(1, 'data');
});

it('limits uploads to 30 per minute per person', function () {
    $admin = adminUser();
    ['task' => $task] = bagProject($admin, [], 3600);
    $this->actingAs($admin);

    foreach (range(1, 30) as $n) {
        attach($task, UploadedFile::fake()->createWithContent("nota-{$n}.txt", "Nota {$n}\n"))->assertCreated();
    }
    attach($task, UploadedFile::fake()->createWithContent('nota-31.txt', "Nota 31\n"))->assertStatus(429);
});

it('brings checklist progress and attachment count in every list without a query per task', function () {
    $admin = adminUser();
    ['project' => $project, 'task' => $task] = bagProject($admin, [$admin], 3600);
    $this->actingAs($admin);
    $this->postJson("/api/v1/tasks/{$task->code}/checklist", ['text' => 'A'])->assertCreated();
    $item = $this->postJson("/api/v1/tasks/{$task->code}/checklist", ['text' => 'B'])->json('data.id');
    $this->putJson("/api/v1/checklist-items/{$item}", ['done' => true])->assertOk();
    attach($task, pdfFile())->assertCreated();

    foreach (["/api/v1/projects/{$project->id}/tasks", '/api/v1/me/tasks', '/api/v1/tasks'] as $url) {
        $this->getJson($url)
            ->assertJsonPath('data.0.checklist_progress', ['done' => 1, 'total' => 2])
            ->assertJsonPath('data.0.attachments_count', 1);
    }
    $this->getJson('/api/v1/me/dashboard')->assertOk();

    $queriesFor = function (string $url) use ($admin): int {
        $this->actingAs($admin->fresh());
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson($url)->assertOk();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };
    $before = $queriesFor("/api/v1/projects/{$project->id}/tasks");

    foreach (range(1, 4) as $n) {
        $more = app(CreateTask::class)($admin, $project, ['title' => "Otra {$n}"], [$admin->id]);
        TaskChecklistItem::query()->create(['task_id' => $more->id, 'text' => 'X', 'position' => 1, 'created_by' => $admin->id]);
        attach($more, pdfFile())->assertCreated();
    }

    expect($queriesFor("/api/v1/projects/{$project->id}/tasks"))->toBe($before);
});

it('accepts any plain text as .txt or .csv (logs, JSON exports, empty files) but never markup', function () {
    $admin = adminUser();
    ['task' => $task] = bagProject($admin, [], 3600);
    $this->actingAs($admin);

    attach($task, UploadedFile::fake()->createWithContent('export.txt', '{"horas": 12, "proyecto": "bhn"}'))
        ->assertCreated()->assertJsonPath('data.mime', 'text/plain');
    attach($task, UploadedFile::fake()->createWithContent('deploy.txt', "#!/bin/bash\nset -e\nphp artisan migrate --force\n"))
        ->assertCreated()->assertJsonPath('data.mime', 'text/plain');
    attach($task, UploadedFile::fake()->createWithContent('vacio.csv', ''))
        ->assertCreated()->assertJsonPath('data.mime', 'text/csv');

    attach($task, UploadedFile::fake()->createWithContent('pagina.txt', '<!DOCTYPE html><html><body><script>alert(1)</script></body></html>'))
        ->assertUnprocessable()->assertJsonPath('data.code', 'attachment_type');
    attach($task, UploadedFile::fake()->createWithContent('logo.csv', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'))
        ->assertUnprocessable()->assertJsonPath('data.code', 'attachment_type');
});

it('strips bidi format characters so a zip cannot pose as a PDF in the list', function () {
    $admin = adminUser();
    ['task' => $task] = bagProject($admin, [], 3600);
    $this->actingAs($admin);

    attach($task, UploadedFile::fake()->createWithContent("factura\u{202E}fdp.txt", 'hola'))
        ->assertCreated()->assertJsonPath('data.name', 'facturafdp.txt');
});
