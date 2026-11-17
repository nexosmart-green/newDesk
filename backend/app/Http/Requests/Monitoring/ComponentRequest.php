<?php

namespace App\Http\Requests\Monitoring;

use App\Domain\StatusPage\Models\Component;
use App\Domain\StatusPage\Status\Parts;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A status component (monitoreo componentInput): each kind validates only its own fields. Without
 * `parts` the backend picks them (D-045); a pm2 `target_owner` is optional (D-089).
 */
class ComponentRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = [
            'kind' => ['required', Rule::in(Component::KINDS)],
            'label' => ['required', 'string', 'max:120'],
            'parts' => ['sometimes', 'nullable', 'array', function (string $attribute, mixed $value, \Closure $fail) {
                if (is_array($value) && (! array_is_list($value) || array_filter($value, fn ($part) => ! is_string($part)) !== [] || Parts::normalize($value) === null)) {
                    $fail('Elegí entre Web, App y CMS.');
                }
            }],
            'position' => ['nullable', 'integer', 'between:0,1000'],
        ];
        $kind = $this->input('kind');
        if (in_array($kind, [Component::HTTP, Component::HEALTH], true)) {
            return [...$rules,
                'url' => ['required', 'string', 'max:512', function (string $attribute, mixed $value, \Closure $fail) {
                    $url = is_string($value) ? parse_url($value) : false;
                    if ($url === false || ! in_array($url['scheme'] ?? '', ['http', 'https'], true) || ($url['host'] ?? '') === '') {
                        $fail('Ingresá una URL completa, por ejemplo https://api.playinn.com/health.');
                    }
                }],
                'slow_ms' => ['nullable', 'integer', 'between:100,60000'],
            ];
        }
        if (in_array($kind, [Component::PM2, Component::SUPERVISOR, Component::TIMER], true)) {
            return [...$rules,
                'server_id' => ['required', 'integer', Rule::exists('monitoring_servers', 'id')->where('company_id', $this->user()->company_id)],
                'target' => ['required', 'string', 'max:255'],
                'target_owner' => ['nullable', 'string', 'max:64'],
                'max_age_hours' => ['nullable', 'integer', 'between:1,720'],
            ];
        }

        return $rules;
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'kind' => 'Tipo inválido.',
            'label' => 'El nombre es obligatorio (hasta 120 caracteres).',
            'parts' => 'Elegí entre Web, App y CMS.',
            'position' => 'Posición entre 0 y 1000.',
            'url' => 'Ingresá una URL completa, por ejemplo https://api.playinn.com/health.',
            'slow_ms' => 'Entre 100 y 60000 ms.',
            'server_id.required' => 'Elegí el servidor.',
            'server_id' => 'Ese servidor no existe.',
            'target' => 'Elegí qué chequear.',
            'target_owner' => 'Usuario inválido.',
            'max_age_hours' => 'Entre 1 y 720 horas.',
        ];
    }

    /** @return array<string, mixed> what SaveComponent reads (parts normalized, absent ones null) */
    public function componentInput(): array
    {
        $data = $this->validated();
        $data['parts'] = isset($data['parts']) ? Parts::normalize($data['parts']) : null;

        return $data;
    }
}
