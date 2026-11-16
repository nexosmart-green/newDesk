<?php

namespace App\Domain\Assistant\Enums;

use App\Domain\Identity\Enums\Permission;

/** RF-13.05: the reports the assistant can hand over as a file. */
enum ReportKind: string
{
    case HoursByProject = 'hours_by_project';
    case HoursByClient = 'hours_by_client';
    case HoursByPerson = 'hours_by_person';
    case BagsRunningOut = 'bags_running_out';
    case OverdueTasks = 'overdue_tasks';

    /** The module that has to be on for the role, as in the web. */
    public function module(): Permission
    {
        return $this->isHours() ? Permission::ModuleReports : Permission::ModuleProjects;
    }

    /** Company-wide hours need `projects.manage`, like the web hours report (RF-10.01). */
    public function isHours(): bool
    {
        return in_array($this, [self::HoursByProject, self::HoursByClient, self::HoursByPerson], true);
    }

    public function usesPeriod(): bool
    {
        return $this->isHours();
    }
}
