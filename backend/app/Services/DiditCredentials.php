<?php

namespace App\Services;

final class DiditCredentials
{
    /** Keep old organizations usable only for their recorded workflows. */
    public static function forWorkflow(string $workflowId): ?array
    {
        if ($workflowId === (string) config('didit.workflow_id')) {
            return [
                'api_key' => config('didit.api_key'),
                'webhook_secret' => config('didit.webhook_secret'),
                'environment' => config('didit.environment'),
            ];
        }

        $legacy = config('didit.legacy_workflows', []);
        return isset($legacy[$workflowId]) && is_array($legacy[$workflowId]) ? $legacy[$workflowId] : null;
    }
}
