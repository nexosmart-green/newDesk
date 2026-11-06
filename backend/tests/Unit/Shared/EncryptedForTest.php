<?php

use App\Domain\Monitoring\Models\Server;
use App\Domain\Shared\Casts\EncryptedFor;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;

function sealedServerToken(string $plain): string
{
    $server = new Server;
    $server->token = $plain;

    return $server->getAttributes()['token'];
}

it('stores the value encrypted and reads it back (CA-13.01)', function () {
    $sealed = sealedServerToken('agent-token-0123456789');

    expect($sealed)->not->toContain('agent-token');
    expect((new Server)->setRawAttributes(['token' => $sealed])->token)->toBe('agent-token-0123456789');
});

it('does not decrypt a value copied into another column or table (H9)', function () {
    $sealed = sealedServerToken('agent-token-0123456789');
    $otherColumn = new class extends Model
    {
        protected $table = 'monitoring_servers';

        protected $casts = ['tls_spki_pin' => EncryptedFor::class];
    };
    $otherTable = new class extends Model
    {
        protected $table = 'portal_projects';

        protected $casts = ['token' => EncryptedFor::class];
    };

    expect(fn () => $otherColumn->setRawAttributes(['tls_spki_pin' => $sealed])->tls_spki_pin)->toThrow(DecryptException::class);
    expect(fn () => $otherTable->setRawAttributes(['token' => $sealed])->token)->toThrow(DecryptException::class);
});

it('keeps null as null', function () {
    $server = new Server;
    $server->token = null;

    expect($server->getAttributes()['token'])->toBeNull()->and($server->token)->toBeNull();
});
