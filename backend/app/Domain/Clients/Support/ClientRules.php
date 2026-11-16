<?php

namespace App\Domain\Clients\Support;

/**
 * Validation of a client's editable fields, shared by the web (SaveClientRequest) and MCP (create/update_client).
 */
final class ClientRules
{
    /** @return array<string, list<string>> */
    public static function save(): array
    {
        return [
            'legal_name' => ['required', 'string', 'max:150'],
            'trade_name' => ['nullable', 'string', 'max:150'],
            'tax_id' => ['nullable', 'string', 'regex:/^\d{2}-?\d{8}-?\d$/'],
            'contact_name' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
