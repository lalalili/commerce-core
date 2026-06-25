<?php

namespace Lalalili\CommerceCore\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Contracts\Auth\Access\Authorizable;

abstract class CrudPermissionPolicy
{
    use HandlesAuthorization;

    abstract protected function permissionResourceName(): string;

    public function viewAny(Authorizable $authUser): bool
    {
        return $authUser->can($this->permissionAbility('ViewAny'));
    }

    public function view(Authorizable $authUser, mixed $record): bool
    {
        return $authUser->can($this->permissionAbility('View'));
    }

    public function create(Authorizable $authUser): bool
    {
        return $authUser->can($this->permissionAbility('Create'));
    }

    public function update(Authorizable $authUser, mixed $record): bool
    {
        return $authUser->can($this->permissionAbility('Update'));
    }

    public function delete(Authorizable $authUser, mixed $record): bool
    {
        return $authUser->can($this->permissionAbility('Delete'));
    }

    public function restore(Authorizable $authUser, mixed $record): bool
    {
        return $authUser->can($this->permissionAbility('Restore'));
    }

    public function forceDelete(Authorizable $authUser, mixed $record): bool
    {
        return $authUser->can($this->permissionAbility('ForceDelete'));
    }

    public function forceDeleteAny(Authorizable $authUser): bool
    {
        return $authUser->can($this->permissionAbility('ForceDeleteAny'));
    }

    public function restoreAny(Authorizable $authUser): bool
    {
        return $authUser->can($this->permissionAbility('RestoreAny'));
    }

    public function replicate(Authorizable $authUser, mixed $record): bool
    {
        return $authUser->can($this->permissionAbility('Replicate'));
    }

    public function reorder(Authorizable $authUser): bool
    {
        return $authUser->can($this->permissionAbility('Reorder'));
    }

    protected function permissionAbility(string $action): string
    {
        return "{$action}:{$this->permissionResourceName()}";
    }
}
