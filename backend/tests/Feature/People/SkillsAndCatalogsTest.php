<?php

use App\Domain\People\Models\Skill;

function skillNamed(int $companyId, string $name): Skill
{
    return Skill::query()->forCompany($companyId)->where('name', $name)->firstOrFail();
}

it('seeds the base catalogs with every new company', function () {
    $admin = adminUser();

    $this->actingAs($admin)->getJson('/api/v1/catalogs/areas')
        ->assertOk()->assertJsonFragment(['name' => 'Dev'])->assertJsonFragment(['name' => 'Administración']);
    $this->actingAs($admin)->getJson('/api/v1/catalogs/nope')->assertNotFound();
});

it('answers "¿quién sabe React?" with a minimum level (RF-01.06)', function () {
    $admin = adminUser();
    $react = skillNamed($admin->company_id, 'React');
    $senior = userWithRole($admin);
    $junior = userWithRole($admin);

    $this->actingAs($senior)->putJson('/api/v1/me/skills', ['skills' => [['id' => $react->id, 'level' => 90]]])->assertOk();
    $this->actingAs($junior)->putJson('/api/v1/me/skills', ['skills' => [['id' => $react->id, 'level' => 30]]])->assertOk();

    $this->actingAs($junior)->getJson("/api/v1/people?skill_id={$react->id}&min_level=50")
        ->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $senior->id)
        ->assertJsonPath('data.0.skills.0.level', 90);
});

it('rejects skills from another company catalog', function () {
    $admin = adminUser('a@nexo.com');
    $foreign = skillNamed(adminUser('b@other.com')->company_id, 'PHP');

    $this->actingAs(userWithRole($admin))->putJson('/api/v1/me/skills', ['skills' => [['id' => $foreign->id, 'level' => 50]]])
        ->assertUnprocessable()->assertJsonPath('data.code', 'skill_unknown');
});

it('lets users.manage administer catalogs and blocks deleting values in use (RF-01.08)', function () {
    $admin = adminUser();
    $person = userWithRole($admin);

    $id = $this->actingAs($admin)->postJson('/api/v1/catalogs/job-titles', ['name' => 'QA'])->assertCreated()->json('data.id');
    $this->actingAs($admin)->postJson('/api/v1/catalogs/job-titles', ['name' => 'QA'])->assertUnprocessable();
    $this->actingAs($admin)->putJson("/api/v1/catalogs/job-titles/{$id}", ['name' => 'QA Analyst'])->assertOk();
    $this->actingAs($admin)->putJson("/api/v1/people/{$person->id}", ['job_title_id' => $id])->assertOk();

    $this->actingAs($admin)->deleteJson("/api/v1/catalogs/job-titles/{$id}")
        ->assertUnprocessable()->assertJsonPath('data.code', 'catalog_item_in_use');
    $this->actingAs($person)->postJson('/api/v1/catalogs/job-titles', ['name' => 'Jefe'])->assertForbidden();
});
