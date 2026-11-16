<?php

namespace App\Domain\Tasks\Support;

/**
 * Input limits shared by the web (FormRequests) and MCP tools, so both reject the same thing.
 */
final class TaskLimits
{
    public const TITLE_MAX = 255;

    public const DESCRIPTION_MAX = 65000;

    public const COMMENT_BODY_MAX = 20000;

    public const ASSIGNEES_MAX = 20;

    public const MENTIONS_MAX = 20;

    public const LABELS_MAX = 20;
}
