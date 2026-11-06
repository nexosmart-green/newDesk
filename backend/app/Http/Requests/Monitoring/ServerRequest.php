<?php

namespace App\Http\Requests\Monitoring;

use App\Domain\Monitoring\Agent\Fingerprint;
use App\Domain\Monitoring\Models\Server;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** RF-13.01 create and update, with monitoreo's rules and messages (serverInput.validate). */
class ServerRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'ip' => self::canonicalIp($this->input('ip')),
            'agent_port' => $this->input('agent_port') ?: 9100,
            'tls_fingerprint' => Fingerprint::normalize($this->input('tls_fingerprint')) ?: null,
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $server = $this->route('server');
        $creating = ! $server instanceof Server;

        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('monitoring_servers')
                ->where('company_id', $this->user()->company_id)
                ->ignore($creating ? null : $server->id)],
            'ip' => ['required', 'ip'],
            'ssh_port' => ['nullable', 'integer', 'between:1,65535'],
            'agent_port' => ['integer', 'between:1,65535'],
            // On update an empty token or fingerprint keeps the stored one.
            'token' => [$creating ? 'required' : 'nullable', 'string', 'min:16', 'max:512'],
            'tls_fingerprint' => [$creating ? 'required' : 'nullable', 'string', 'regex:/^[0-9a-f]{64}$/'],
            'enabled' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.unique' => 'Ya existe un servidor con ese nombre.',
            'name' => 'El nombre es obligatorio (máximo 100 caracteres).',
            'ip' => 'Ingresá una IP válida.',
            'ssh_port' => 'Puerto inválido.',
            'agent_port' => 'Puerto inválido.',
            'token.required' => 'El token del agente es obligatorio.',
            'token.min' => 'El token es demasiado corto.',
            'tls_fingerprint' => 'El fingerprint debe ser el SHA-256 en hexadecimal (64 caracteres).',
        ];
    }

    /** "::ffff:1.2.3.4" is 1.2.3.4 and IPv6 is stored in its short form, as monitoreo does. */
    private static function canonicalIp(mixed $ip): mixed
    {
        $packed = is_string($ip) ? @inet_pton($ip) : false;
        if ($packed === false) {
            return $ip;
        }
        $canonical = inet_ntop($packed);
        $mapped = str_starts_with($canonical, '::ffff:') ? substr($canonical, 7) : '';

        return filter_var($mapped, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false ? $mapped : $canonical;
    }
}
