<?php

use App\Domain\Identity\DefaultRoles;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Support\ReportFixtures as Reports;
use Tests\Support\StatusPageFixtures as Fixtures;

const ROADMAP = '/api/v1/monitoring/portal-admin/roadmap';

/** The report ids of the caller's roadmap, in order (monitoreo roadmapIDs). @return list<int> */
function roadmapIds(TestResponse $response): array
{
    return array_column($response->assertOk()->json('data'), 'id');
}

// httpapi/roadmap_test.go TestRoadmapIsPersonal (D-064, D-066)
it('keeps each developer roadmap in order and private to its owner (CA-13.19)', function () {
    Storage::fake('local');
    $admin = provisionCompany('admin@nexosmart.com.ar');
    $dev = User::factory()->forRole(roleNamed($admin, DefaultRoles::DEVELOPER))->create(['email' => 'dev@nexosmart.com.ar']);
    $project = Fixtures::project($admin, 'acme', 'Acme');
    $owner = Reports::signIn(Reports::clientUser($project, 'dueno@acme.test'));
    [$first, $second] = array_map(fn (string $text) => Reports::post('acme', ['description' => $text, 'urgency' => 'annoying'])->assertCreated()->json('data.report.id'),
        ['No carga el carrito.', 'El login tarda.']);

    Reports::signIn($dev);
    expect(roadmapIds($this->getJson(ROADMAP)))->toBe([]);
    $this->postJson(ROADMAP, ['report_id' => $second])->assertOk()->assertJsonPath('message', 'Sumado a tu roadmap.');
    $this->postJson(ROADMAP, ['report_id' => $first]);
    expect(roadmapIds($this->postJson(ROADMAP, ['report_id' => $second])))->toBe([$second, $first]) // already there: stays
        ->and($this->getJson(ROADMAP)->json('data.0'))->toMatchArray(['id' => $second, 'client_name' => 'Acme', 'project_name' => 'Acme', 'status' => 'received']);
    $this->postJson(ROADMAP, ['report_id' => 9999])->assertNotFound()->assertJsonPath('message', 'Reporte no encontrado.');

    expect(roadmapIds($this->putJson(ROADMAP, ['report_ids' => [$first, $second]])))->toBe([$first, $second]);
    foreach ([[$first], [$first, $second, $second], [$first, 9999], []] as $stale) {
        $this->putJson(ROADMAP, ['report_ids' => $stale])->assertStatus(409)
            ->assertJsonPath('message', 'Tu roadmap cambió mientras lo ordenabas. Volvé a intentarlo.');
    }

    // Private: the admin sees only their own (empty) roadmap and cannot touch the dev's.
    Reports::signIn($admin);
    expect(roadmapIds($this->getJson(ROADMAP)))->toBe([]);
    $this->deleteJson(ROADMAP."/{$first}")->assertOk();
    $this->postJson(ROADMAP, ['report_id' => $second]);
    expect(roadmapIds($this->getJson(ROADMAP)))->toBe([$second]);
    Reports::signIn($dev);
    expect(roadmapIds($this->getJson(ROADMAP)))->toBe([$first, $second])
        ->and(roadmapIds($this->deleteJson(ROADMAP."/{$first}")->assertJsonPath('message', 'Quitado de tu roadmap.')))->toBe([$second]);

    // A resolved report stays on it (struck through in the browser) until it is taken off.
    $this->putJson("/api/v1/monitoring/portal-admin/reports/{$second}", ['status' => 'resolved'])->assertOk();
    $this->getJson(ROADMAP)->assertJsonPath('data.0.status', 'resolved');

    // Clients never reach it.
    Reports::signIn($owner);
    $this->getJson(ROADMAP)->assertForbidden();
});
