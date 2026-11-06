<?php

namespace App\Http\Requests\Monitoring;

use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\User;
use App\Domain\Monitoring\Contracts\DiscordGateway;
use App\Domain\Projects\Actions\SaveProject;
use App\Domain\Projects\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * monitoreo's client project forms: POST portal-admin/clients/{id}/projects (create) and PUT
 * portal-admin/projects/{id} (the whole form), plus newDesk's code (CA-03.01). A Discord channel is
 * one the bot lists (D-060), or one the bot creates on create: not while notices are off (CA-13.20).
 */
class PortalProjectRequest extends FormRequest
{
    private const CHANNEL_NOT_LISTED = 'Elegí un canal de la lista.';

    private const NO_BOT = 'El bot de Discord no está configurado.';

    private const NOTICES_OFF = 'Los avisos a Discord están apagados mientras convive con monitoreo: elegí un canal existente.';

    protected function prepareForValidation(): void
    {
        foreach (['primary_color', 'domain'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => rtrim(strtolower(trim($this->input($field))), $field === 'domain' ? '.' : '')]);
            }
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $project = $this->route('project');
        $unique = fn () => Rule::unique('projects')->where('company_id', $this->user()->company_id)
            ->ignore($project instanceof Project ? $project->id : null);
        $slug = ['string', 'max:60', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/', Rule::notIn(SaveProject::RESERVED_SLUGS), $unique()];
        $common = [
            'name' => ['required', 'string', 'max:120'],
            'code_prefix' => ['required', 'string', 'regex:/^[a-z0-9]{2,12}$/', $unique()],
            'discord_channel_id' => ['nullable', 'string'],
        ];

        if (! $project instanceof Project) {
            return [...$common, 'slug' => ['nullable', ...$slug], 'lead_user_id' => ['nullable', 'integer'], 'create_discord_channel' => ['sometimes', 'boolean']];
        }

        return [
            ...$common,
            'slug' => ['required', ...$slug],
            'primary_color' => ['nullable', 'string', 'regex:/^#[0-9a-f]{6}$/'],
            'domain' => ['nullable', 'string', 'max:253', 'regex:/^([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/'],
            'domain_expires_manual' => ['nullable', 'date_format:Y-m-d'],
            'active' => ['sometimes', 'boolean'],
            'errors_url' => ['nullable', 'string', 'max:512', function (string $attribute, mixed $value, \Closure $fail) {
                $url = is_string($value) ? (parse_url($value) ?: []) : [];
                $scheme = $url['scheme'] ?? '';
                if (($scheme !== 'https' && ! ($scheme === 'http' && app()->isLocal())) || ($url['host'] ?? '') === '') {
                    $fail('Usá la URL https completa del /api/errors del proyecto.');
                }
            }],
            'errors_token' => ['nullable', 'string', 'max:500'],
            'developers' => ['sometimes', 'nullable', 'array'],
            'developers.lead_user_id' => ['nullable', 'integer'],
            'developers.secondary_user_ids' => ['nullable', 'array'],
            'developers.secondary_user_ids.*' => ['integer'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name' => 'El nombre es obligatorio (hasta 120 caracteres).',
            'slug.unique' => SaveProject::SLUG_TAKEN,
            'slug.not_in' => SaveProject::SLUG_TAKEN,
            'slug' => 'Usá minúsculas, números y guiones (hasta 60).',
            'code_prefix.unique' => 'Ya hay un proyecto con ese código.',
            'code_prefix' => 'El código va en minúsculas, de 2 a 12 letras o números.',
            'primary_color' => 'Usá un color #rrggbb.',
            'domain' => 'Ingresá solo el dominio, por ejemplo playinn.com.',
            'domain_expires_manual' => 'Usá una fecha AAAA-MM-DD.',
            'errors_url.string' => 'Ingresá una URL.',
            'errors_url' => 'Usá la URL https completa del /api/errors del proyecto.',
            'discord_channel_id' => self::CHANNEL_NOT_LISTED,
            'lead_user_id' => 'Elegí un usuario activo del equipo.',
            'developers' => 'Elegí usuarios activos del equipo.',
            'developers.*' => 'Elegí usuarios activos del equipo.',
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $project = $this->route('project');
            $current = $project instanceof Project ? $project->discord_channel_id : null;
            $channel = $this->input('discord_channel_id');
            $create = $this->boolean('create_discord_channel');
            $discord = app(DiscordGateway::class);
            if (filled($channel) && ($create || ($channel !== $current && ! $discord->allowed($channel)))) {
                $validator->errors()->add('discord_channel_id', self::CHANNEL_NOT_LISTED);
            }
            if ($create && ! $discord->configured()) {
                $validator->errors()->add('discord_channel_id', self::NO_BOT);
            } elseif ($create && ! config('newdesk.monitoring.notify')) {
                $validator->errors()->add('discord_channel_id', self::NOTICES_OFF);
            }
            if ($this->filled('lead_user_id') && ! $this->teamUsers([$this->integer('lead_user_id')])) {
                $validator->errors()->add('lead_user_id', 'Elegí un usuario activo del equipo.');
            }
            $developers = $this->input('developers');
            if (is_array($developers) && ! $this->teamUsers([...($developers['secondary_user_ids'] ?? []), ...array_filter([$developers['lead_user_id'] ?? null])])) {
                $validator->errors()->add('developers', 'Elegí usuarios activos del equipo.');
            }
            if ($project instanceof Project) {
                $this->checkErrorsToken($validator, $project);
            }
        }];
    }

    /** The PUT as UpdateStatusProject reads it: absent keys keep what is stored. @return array<string, mixed> */
    public function updateInput(Project $project): array
    {
        $data = $this->validated();
        $input = [
            'name' => $data['name'],
            'slug' => $data['slug'],
            'code_prefix' => $data['code_prefix'],
            'primary_color' => $data['primary_color'] ?? null,
            'domain' => $data['domain'] ?? null,
            'domain_expires_manual' => $data['domain_expires_manual'] ?? null,
            'portal_active' => array_key_exists('active', $data) ? (bool) $data['active'] : $project->portal_active,
        ];
        foreach (['errors_url', 'discord_channel_id'] as $field) {
            if (array_key_exists($field, $this->all())) {
                $input[$field] = $data[$field] ?? null; // null or "" clears it
            }
        }
        if (filled($data['errors_token'] ?? null)) {
            $input['errors_token'] = $data['errors_token'];
        }
        if (is_array($data['developers'] ?? null)) {
            $input['developers'] = ['lead_user_id' => $data['developers']['lead_user_id'] ?? null,
                'secondary_user_ids' => array_map('intval', $data['developers']['secondary_user_ids'] ?? [])];
        }

        return $input;
    }

    /** The stored token goes only to the URL it was entered for (D-093 H3). */
    private function checkErrorsToken(Validator $validator, Project $project): void
    {
        $url = array_key_exists('errors_url', $this->all()) ? ($this->input('errors_url') ?: null) : $project->errors_url;
        $newToken = filled($this->input('errors_token'));
        if ($newToken && $url === null) {
            $validator->errors()->add('errors_token', 'Cargá también la URL.');
        }
        $moved = $url !== null && $url !== $project->errors_url;
        if ($moved && ! $newToken && $project->getRawOriginal('errors_token') !== null) {
            $validator->errors()->add('errors_token', 'Cambiaste la URL: volvé a cargar el token.');
        }
    }

    /** @param  list<int>  $ids  active team users of this company, all of them */
    private function teamUsers(array $ids): bool
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));

        return $ids === [] || User::query()->whereIn('id', $ids)->where('company_id', $this->user()->company_id)
            ->whereNull('client_id')->where('status', UserStatus::Active)->count() === count($ids);
    }
}
