<?php

namespace App\Http\Requests\People;

use Illuminate\Foundation\Http\FormRequest;

/**
 * `PUT /me/skills` (RF-01.06): the full list of the person's skills, each with a 0–100 level.
 */
class SetUserSkillsRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'skills' => ['present', 'array', 'max:50'],
            'skills.*.id' => ['required', 'integer', 'distinct'],
            'skills.*.level' => ['required', 'integer', 'between:0,100'],
        ];
    }

    /** @return array<int, int> level by skill id */
    public function levelsBySkillId(): array
    {
        return collect($this->input('skills'))
            ->mapWithKeys(fn (array $skill) => [(int) $skill['id'] => (int) $skill['level']])
            ->all();
    }
}
