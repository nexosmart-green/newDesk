<?php

namespace App\Mcp\Tools;

use App\Domain\Identity\Models\User;
use App\Mcp\Support\PreparedWrite;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

/**
 * Base of every tool that changes data. Writing is split in two steps so a write can be shown to a person
 * before it happens (RF-13.02): `prepare()` validates, resolves and authorizes WITHOUT writing and describes
 * the change; the returned PreparedWrite runs the domain action. Read tools extend NewDeskTool with #[IsReadOnly].
 */
abstract class NewDeskWriteTool extends NewDeskTool
{
    abstract public function prepare(Request $request, User $user): PreparedWrite;

    final protected function run(Request $request, User $user): Response
    {
        return $this->prepare($request, $user)->execute();
    }
}
