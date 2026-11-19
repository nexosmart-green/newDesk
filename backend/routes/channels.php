<?php

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\Broadcast;

// Private channels per module. Auth endpoint: /api/v1/broadcasting/auth (bootstrap/app.php).

// RF-13: server status, snapshots and metrics of one company, for its team with monitoring.view.
Broadcast::channel('monitoring.{companyId}', fn (User $user, int $companyId) => ! $user->isClient()
    && $user->company_id === $companyId
    && $user->hasPermission(Permission::MonitoringView));

// RNF-12: "something of yours changed" (timer, entries, bell), only for that person.
Broadcast::channel('user.{userId}', fn (User $user, int $userId) => $user->id === $userId);

// RF-13.07: one client's portal events, only for that client's users.
Broadcast::channel('portal.client.{clientId}', fn (User $user, int $clientId) => $user->client_id === $clientId);
