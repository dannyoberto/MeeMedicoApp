<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReactivateUserAction
{
    public function execute(User $user, ?User $actor): void
    {
        if ($user->status === UserStatus::Active) {
            return;
        }

        DB::transaction(function () use ($user, $actor) {
            $user->forceFill(['status' => UserStatus::Active])->save();

            activity()
                ->performedOn($user)
                ->causedBy($actor)
                ->event('user.reactivated')
                ->log('user.reactivated');
        });
    }
}
