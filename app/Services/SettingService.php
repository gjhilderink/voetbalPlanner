<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;

class SettingService
{
    public function getMcpSettings(): array
    {
        return [
            'mcp_base_url' => Setting::get('mcp_base_url', ''),
            'mcp_timeout' => Setting::get('mcp_timeout', 30),
            'last_sync_at' => Setting::get('last_sync_at', null),
        ];
    }

    public function saveMcpSettings(array $data): void
    {
        Setting::set('mcp_base_url', $data['mcp_base_url'] ?? '', 'mcp');
        Setting::set('mcp_timeout', $data['mcp_timeout'] ?? 30, 'mcp');

        if (!empty($data['mcp_api_key'])) {
            Setting::set('mcp_api_key', $data['mcp_api_key'], 'mcp', true);
        }
    }
}
