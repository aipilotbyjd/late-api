<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Credential;
use Illuminate\Auth\Access\HandlesAuthorization;

class CredentialPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any credentials.
     */
    public function viewAny(User $user): bool
    {
        return true; // Users can always list their own credentials
    }

    /**
     * Determine whether the user can view the credential.
     */
    public function view(User $user, Credential $credential): bool
    {
        // User owns the credential or has access through team
        return $credential->user_id === $user->id ||
            ($credential->team_id && $user->teams->contains('id', $credential->team_id));
    }

    /**
     * Determine whether the user can create credentials.
     */
    public function create(User $user): bool
    {
        return true; // Any authenticated user can create credentials
    }

    /**
     * Determine whether the user can update the credential.
     */
    public function update(User $user, Credential $credential): bool
    {
        // Only the owner can update the credential
        return $credential->user_id === $user->id;
    }

    /**
     * Determine whether the user can delete the credential.
     */
    public function delete(User $user, Credential $credential): bool
    {
        // Only the owner can delete the credential
        return $credential->user_id === $user->id;
    }

    /**
     * Determine whether the user can restore the credential.
     */
    public function restore(User $user, Credential $credential): bool
    {
        return $credential->user_id === $user->id;
    }

    /**
     * Determine whether the user can permanently delete the credential.
     */
    public function forceDelete(User $user, Credential $credential): bool
    {
        return $credential->user_id === $user->id;
    }
}
