<?php

namespace App\Twig;

use App\Entity\ActivityLog;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class AppExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('format_activity_action', [$this, 'formatActivityAction']),
            new TwigFilter('format_target_data', [$this, 'formatTargetData']),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('get_activity_icon', [$this, 'getActivityIcon']),
            new TwigFunction('get_activity_icon_class', [$this, 'getActivityIconClass']),
            new TwigFunction('get_activity_message', [$this, 'getActivityMessage']),
        ];
    }

    public function formatActivityAction(string $action): string
    {
        // Convert action to a more readable format
        $action = strtolower($action);
        $action = str_replace('_', ' ', $action);
        return ucwords($action);
    }

    public function formatTargetData($targetData): string
    {
        if (is_array($targetData)) {
            return json_encode($targetData, JSON_PRETTY_PRINT);
        }

        return (string) $targetData;
    }

    public function getActivityIcon(string $action): string
    {
        $icon = '';
        
        if (strpos($action, 'CREATE') === 0) {
            $icon = '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>';
        } elseif (strpos($action, 'UPDATE') === 0) {
            $icon = '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
            </svg>';
        } elseif (strpos($action, 'DELETE') === 0) {
            $icon = '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
            </svg>';
        } elseif (strpos($action, 'LOGIN') !== false) {
            $icon = '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
            </svg>';
        } elseif (strpos($action, 'LOGOUT') !== false) {
            $icon = '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
            </svg>';
        } else {
            $icon = '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>';
        }
        
        return $icon;
    }
    
    public function getActivityIconClass(string $action): string
    {
        if (strpos($action, 'CREATE') === 0) {
            return 'create';
        } elseif (strpos($action, 'UPDATE') === 0) {
            return 'update';
        } elseif (strpos($action, 'DELETE') === 0) {
            return 'delete';
        } elseif (strpos($action, 'LOGIN') !== false) {
            return 'login';
        } elseif (strpos($action, 'LOGOUT') !== false) {
            return 'logout';
        }
        
        return 'info';
    }
    
    public function getActivityMessage(ActivityLog $log): string
    {
        $action = $log->getAction();
        $targetData = $log->getTargetData() ?? [];
        $username = $log->getUsername() ?? 'System';
        
        switch ($action) {
            case 'USER_LOGIN':
                return "<strong>{$username}</strong> logged in to the system";
                
            case 'USER_LOGOUT':
                return "<strong>{$username}</strong> logged out";
                
            case 'USER_CREATED':
                $createdUser = $targetData['email'] ?? 'a user';
                $by = $targetData['createdBy'] ? " by <strong>{$username}</strong>" : '';
                return "New user <strong>{$createdUser}</strong> was created{$by}";
                
            case 'USER_UPDATED':
                $changes = [];
                foreach (($targetData['changes'] ?? []) as $field => $change) {
                    $changes[] = "<strong>{$field}</strong> from " . $this->formatValue($change['old']) . " to " . $this->formatValue($change['new']);
                }
                $changesText = !empty($changes) ? ": " . implode(", ", $changes) : "";
                return "<strong>{$username}</strong> updated user <strong>" . (isset($targetData['email']) ? $targetData['email'] : 'unknown') . "</strong>{$changesText}";
                
            case 'USER_DELETED':
                $deletedUser = $targetData['email'] ?? 'a user';
                $by = $targetData['deletedBy'] ? " by <strong>{$username}</strong>" : '';
                return "User <strong>{$deletedUser}</strong> was deleted{$by}";
                
            case strpos($action, '_CREATED') !== false:
                $entity = $this->getEntityNameFromAction($action);
                $by = $targetData['createdBy'] ? " by <strong>{$username}</strong>" : '';
                return "New {$entity} was created{$by}";
                
            case strpos($action, '_UPDATED') !== false:
                $entity = $this->getEntityNameFromAction($action);
                $changes = [];
                foreach (($targetData['changes'] ?? []) as $field => $change) {
                    $changes[] = "<strong>{$field}</strong> from " . $this->formatValue($change['old']) . " to " . $this->formatValue($change['new']);
                }
                $changesText = !empty($changes) ? ": " . implode(", ", $changes) : "";
                return "<strong>{$username}</strong> updated {$entity}{$changesText}";
                
            case strpos($action, '_DELETED') !== false:
                $entity = $this->getEntityNameFromAction($action);
                $by = $targetData['deletedBy'] ? " by <strong>{$username}</strong>" : '';
                return "{$entity} was deleted{$by}";
                
            default:
                return "<strong>{$username}</strong> performed action: " . strtolower(str_replace('_', ' ', $action));
        }
    }
    
    private function getEntityNameFromAction(string $action): string
    {
        return strtolower(str_replace(['_CREATED', '_UPDATED', '_DELETED'], '', $action));
    }
    
    private function formatValue($value)
    {
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }
        
        if (is_array($value)) {
            return json_encode($value);
        }
        
        if ($value === null) {
            return 'null';
        }
        
        return (string) $value;
    }
}
