<?php

use Illuminate\Contracts\Auth\Access\Authorizable;
use Lalalili\CommerceCore\Policies\CrudPermissionPolicy;

class CrudPermissionPolicyTestPolicy extends CrudPermissionPolicy
{
    protected function permissionResourceName(): string
    {
        return 'Product';
    }
}

class CrudPermissionPolicyTestUser implements Authorizable
{
    /**
     * @var list<string>
     */
    public array $checkedAbilities = [];

    /**
     * @param  list<string>  $allowedAbilities
     */
    public function __construct(private readonly array $allowedAbilities = []) {}

    public function can($abilities, $arguments = []): bool
    {
        $ability = is_array($abilities) ? (string) ($abilities[0] ?? '') : (string) $abilities;

        $this->checkedAbilities[] = $ability;

        return in_array($ability, $this->allowedAbilities, true);
    }

    public function canAny($abilities, $arguments = []): bool
    {
        foreach ((array) $abilities as $ability) {
            if ($this->can($ability, $arguments)) {
                return true;
            }
        }

        return false;
    }

    public function cant($abilities, $arguments = []): bool
    {
        return ! $this->can($abilities, $arguments);
    }

    public function cannot($abilities, $arguments = []): bool
    {
        return $this->cant($abilities, $arguments);
    }
}

it('maps crud policy methods to host permission ability names', function (): void {
    $policy = new CrudPermissionPolicyTestPolicy;
    $user = new CrudPermissionPolicyTestUser([
        'ViewAny:Product',
        'View:Product',
        'Create:Product',
        'Update:Product',
        'Delete:Product',
        'Restore:Product',
        'ForceDelete:Product',
        'ForceDeleteAny:Product',
        'RestoreAny:Product',
        'Replicate:Product',
        'Reorder:Product',
    ]);
    $record = new stdClass;

    expect($policy->viewAny($user))->toBeTrue()
        ->and($policy->view($user, $record))->toBeTrue()
        ->and($policy->create($user))->toBeTrue()
        ->and($policy->update($user, $record))->toBeTrue()
        ->and($policy->delete($user, $record))->toBeTrue()
        ->and($policy->restore($user, $record))->toBeTrue()
        ->and($policy->forceDelete($user, $record))->toBeTrue()
        ->and($policy->forceDeleteAny($user))->toBeTrue()
        ->and($policy->restoreAny($user))->toBeTrue()
        ->and($policy->replicate($user, $record))->toBeTrue()
        ->and($policy->reorder($user))->toBeTrue()
        ->and($user->checkedAbilities)->toBe([
            'ViewAny:Product',
            'View:Product',
            'Create:Product',
            'Update:Product',
            'Delete:Product',
            'Restore:Product',
            'ForceDelete:Product',
            'ForceDeleteAny:Product',
            'RestoreAny:Product',
            'Replicate:Product',
            'Reorder:Product',
        ]);
});

it('denies policy methods when the host user cannot the resolved ability', function (): void {
    $policy = new CrudPermissionPolicyTestPolicy;
    $user = new CrudPermissionPolicyTestUser;

    expect($policy->viewAny($user))->toBeFalse()
        ->and($user->checkedAbilities)->toBe(['ViewAny:Product']);
});
