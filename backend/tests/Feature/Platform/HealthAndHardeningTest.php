<?php

it('answers the root with a neutral status and no stack headers', function () {
    $response = $this->get('/');

    $response->assertOk()->assertExactJson(['status' => 'ok']);
    expect($response->headers->has('X-Powered-By'))->toBeFalse()
        ->and($response->headers->has('Server'))->toBeFalse();
});

it('reports health with every service up', function () {
    $this->getJson('/api/health')
        ->assertOk()
        ->assertJsonPath('status', 'ok')
        ->assertJsonPath('services.database', true);
});

it('protects the error log with ERRORS_TOKEN', function () {
    $this->getJson('/api/errors')->assertUnauthorized();
    $this->getJson('/api/errors?date=1999-01-01', ['Authorization' => 'Bearer wrong'])->assertUnauthorized();
    $this->getJson('/api/errors?date=1999-01-01', ['Authorization' => 'Bearer test-errors-token'])->assertNotFound()
        ->assertJsonStructure(['error', 'available_dates']);
});

it('wraps api errors in the standard envelope', function () {
    $this->getJson('/api/v1/me')->assertUnauthorized()->assertExactJson(['data' => null, 'message' => 'Tu sesión se cerró. Ingresá de nuevo para seguir.', 'errors' => null]);
    $this->getJson('/api/v1/nope')->assertNotFound()->assertExactJson(['data' => null, 'message' => 'No existe o no tenés acceso. Volvé al listado y elegilo de nuevo.', 'errors' => null]);
});
