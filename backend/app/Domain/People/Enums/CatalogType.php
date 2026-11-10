<?php

namespace App\Domain\People\Enums;

use App\Domain\People\Models\Area;
use App\Domain\People\Models\CatalogItem;
use App\Domain\People\Models\EmploymentType;
use App\Domain\People\Models\JobTitle;
use App\Domain\People\Models\Skill;

/**
 * The URL slug of each administrable catalog and where it lives.
 */
enum CatalogType: string
{
    case JobTitles = 'job-titles';
    case Areas = 'areas';
    case EmploymentTypes = 'employment-types';
    case Skills = 'skills';

    /** @return class-string<CatalogItem> */
    public function modelClass(): string
    {
        return match ($this) {
            self::JobTitles => JobTitle::class,
            self::Areas => Area::class,
            self::EmploymentTypes => EmploymentType::class,
            self::Skills => Skill::class,
        };
    }

    public function table(): string
    {
        return (new ($this->modelClass()))->getTable();
    }

    /** Column that references this catalog, and the table holding it, to block deleting values in use. */
    public function usage(): array
    {
        return match ($this) {
            self::JobTitles => ['employee_profiles', 'job_title_id'],
            self::Areas => ['employee_profiles', 'area_id'],
            self::EmploymentTypes => ['employee_profiles', 'employment_type_id'],
            self::Skills => ['user_skills', 'skill_id'],
        };
    }

    /** Starting values for a new company (P-08 defaults). @return list<string> */
    public function defaults(): array
    {
        return match ($this) {
            self::JobTitles => ['Desarrollo', 'Diseño', 'Marketing', 'Community Manager', 'Administración'],
            self::Areas => ['Dev', 'Diseño', 'Marketing', 'CM', 'Administración'],
            self::EmploymentTypes => ['Relación de dependencia', 'Monotributo', 'Freelance', 'Pasante'],
            self::Skills => ['PHP', 'Laravel', 'React', 'TypeScript', 'Figma', 'Meta Ads', 'Google Ads', 'SEO'],
        };
    }
}
