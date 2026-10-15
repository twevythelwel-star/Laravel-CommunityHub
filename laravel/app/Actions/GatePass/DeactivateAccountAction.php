<?php

namespace App\Actions\GatePass;

use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Deactivating an account, by its holder or by an administrator: it is
 * marked inactive, its gate passes are revoked and its API tokens deleted,
 * and the security log notes who did it. The record is kept, so the
 * account can be reactivated (a new pass is issued on its next sign-in).
 *
 * Self-deactivation always did all of this; deactivation from the directory
 * only deleted the tokens, leaving the pass recorded as active and
 * deactivated_at unset.
 */
class DeactivateAccountAction
{
    public function execute(User $account, User $by, ?string $reason = null): void
    {
        $account->forceFill(['status' => 'Inactive', 'deactivated_at' => $account->deactivated_at ?? now()])->save();

        $revocation = $by->is($account) ? 'Account deactivated by holder' : 'Account deactivated by administration';

        // ->get() matters: the higher-order ->each-> proxy exists on
        // Collection, not on Builder.
        $account->gatePasses()->active()->get()->each->revoke($by, $revocation);
        $account->tokens()->delete();

        Log::channel('security')->notice('Account deactivated', [
            'uid' => $account->uid,
            'by' => $by->uid,
            'reason' => $reason,
        ]);
    }
}
