<?php

use App\Domain\Monitoring\Import\LegacySecretBox;
use Tests\Support\GoSealedTokens;

it('opens a token sealed by monitoreo for its row (CA-13.24)', function () {
    $box = new LegacySecretBox(GoSealedTokens::MASTER_KEY);

    expect($box->decrypt(hex2bin(GoSealedTokens::FOR_SERVER_7), LegacySecretBox::row('servers.token_enc', 7)))
        ->toBe(GoSealedTokens::FOR_SERVER_7_PLAIN);
});

it('opens a token sealed before H9, without a row', function () {
    $box = new LegacySecretBox(GoSealedTokens::MASTER_KEY);

    expect($box->decrypt(hex2bin(GoSealedTokens::WITHOUT_ROW), LegacySecretBox::row('servers.token_enc', 3)))
        ->toBe(GoSealedTokens::WITHOUT_ROW_PLAIN);
});

it('refuses a token moved to another row, a tampered one and a wrong key', function () {
    $box = new LegacySecretBox(GoSealedTokens::MASTER_KEY);
    $sealed = hex2bin(GoSealedTokens::FOR_SERVER_7);
    $tampered = $sealed;
    $tampered[strlen($tampered) - 1] = chr(ord($tampered[strlen($tampered) - 1]) ^ 1);

    expect(fn () => $box->decrypt($sealed, LegacySecretBox::row('servers.token_enc', 8)))->toThrow(RuntimeException::class);
    expect(fn () => $box->decrypt($tampered, LegacySecretBox::row('servers.token_enc', 7)))->toThrow(RuntimeException::class);
    expect(fn () => (new LegacySecretBox(base64_encode(str_repeat("\x08", 32))))->decrypt($sealed, 'servers.token_enc:7'))
        ->toThrow(RuntimeException::class);
    expect(fn () => new LegacySecretBox('short'))->toThrow(InvalidArgumentException::class);
});
