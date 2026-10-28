<?php

namespace App\Services;

use App\Models\AuditLog;

class AuditService
{
    /**
     * Record an audit log entry for significant business operations.
     *
     * @param  array<string, mixed>|null  $metadata
     */
    public function log(
        string $action,
        string $entityType,
        string|int $entityId,
        ?array $metadata = null,
        ?string $userId = null,
        ?string $ipAddress = null
    ): AuditLog {
        return AuditLog::query()->create([
            'user_id' => $userId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => (string) $entityId,
            'metadata' => $metadata,
            'ip_address' => $ipAddress,
        ]);
    }
}
