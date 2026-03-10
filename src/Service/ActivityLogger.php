<?php

namespace App\Service;

use App\Entity\ActivityLog;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

class ActivityLogger
{
    private EntityManagerInterface $entityManager;
    private Security $security;

    public function __construct(EntityManagerInterface $entityManager, Security $security)
    {
        $this->entityManager = $entityManager;
        $this->security = $security;
    }

    public function log(string $action, array $targetData = [], ?User $user = null): void
    {
        if ($user === null) {
            $user = $this->security->getUser();
        }
        
        $log = new ActivityLog();
        $log->setAction($action);
        
        // Format target data as string (e.g., "Product: Laptop Asus (ID: 14)")
        $targetDataString = $this->formatTargetData($action, $targetData);
        $log->setTargetData(['formatted' => $targetDataString, 'raw' => $targetData]);
        
        if ($user instanceof User) {
            $log->setUser($user);
        }

        $this->entityManager->persist($log);
        $this->entityManager->flush();
    }

    private function formatTargetData(string $action, array $targetData): string
    {
        // Format based on action type
        if ($action === 'CREATE' || $action === 'UPDATE' || $action === 'DELETE') {
            // Check entity type
            if (isset($targetData['entityType'])) {
                $entityType = $targetData['entityType'];
                $entityId = $targetData['id'] ?? 'Unknown';
                $entityName = $targetData['name'] ?? null;
                
                if ($entityName) {
                    return "{$entityType}: {$entityName} (ID: {$entityId})";
                } else {
                    return "{$entityType} (ID: {$entityId})";
                }
            } elseif (isset($targetData['userId']) || isset($targetData['email'])) {
                // User operations
                $email = $targetData['email'] ?? 'Unknown';
                $userId = $targetData['userId'] ?? $targetData['id'] ?? 'Unknown';
                return "User: {$email} (ID: {$userId})";
            }
        } elseif ($action === 'LOGIN' || $action === 'LOGOUT') {
            $email = $targetData['email'] ?? 'Unknown';
            return "User: {$email}";
        }
        
        // Default format
        if (isset($targetData['id'])) {
            $entityType = $targetData['entityType'] ?? 'Record';
            $entityName = $targetData['name'] ?? null;
            if ($entityName) {
                return "{$entityType}: {$entityName} (ID: {$targetData['id']})";
            }
            return "{$entityType} (ID: {$targetData['id']})";
        }
        
        return json_encode($targetData);
    }

    // Convenience methods for common log actions
    public function logLogin(User $user): void
    {
        $this->log('LOGIN', [
            'userId' => $user->getId(),
            'email' => $user->getEmail()
        ], $user);
    }

    public function logLogout(User $user): void
    {
        $this->log('LOGOUT', [
            'userId' => $user->getId(),
            'email' => $user->getEmail()
        ], $user);
    }

    public function logUserCreated(User $createdUser, ?User $creator = null): void
    {
        $this->log('CREATE', [
            'userId' => $createdUser->getId(),
            'email' => $createdUser->getEmail(),
            'roles' => $createdUser->getRoles(),
            'createdBy' => $creator ? $creator->getId() : null
        ], $creator);
    }

    public function logUserUpdated(User $updatedUser, array $changes, ?User $updater = null): void
    {
        $this->log('UPDATE', [
            'userId' => $updatedUser->getId(),
            'email' => $updatedUser->getEmail(),
            'changes' => $changes,
            'updatedBy' => $updater ? $updater->getId() : null
        ], $updater);
    }

    public function logUserDeleted(User $deletedUser, ?User $deleter = null): void
    {
        $this->log('DELETE', [
            'userId' => $deletedUser->getId(),
            'email' => $deletedUser->getEmail(),
            'deletedBy' => $deleter ? $deleter->getId() : null
        ], $deleter);
    }

    public function logRecordCreated(string $entityType, $entityId, ?string $entityName = null, ?User $creator = null): void
    {
        $this->log('CREATE', [
            'entityType' => $entityType,
            'id' => $entityId,
            'name' => $entityName,
            'createdBy' => $creator ? $creator->getId() : null
        ], $creator);
    }

    public function logRecordUpdated(string $entityType, $entityId, ?string $entityName = null, array $changes = [], ?User $updater = null): void
    {
        $this->log('UPDATE', [
            'entityType' => $entityType,
            'id' => $entityId,
            'name' => $entityName,
            'changes' => $changes,
            'updatedBy' => $updater ? $updater->getId() : null
        ], $updater);
    }

    public function logRecordDeleted(string $entityType, $entityId, ?string $entityName = null, ?User $deleter = null): void
    {
        $this->log('DELETE', [
            'entityType' => $entityType,
            'id' => $entityId,
            'name' => $entityName,
            'deletedBy' => $deleter ? $deleter->getId() : null
        ], $deleter);
    }
}
