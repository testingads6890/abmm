<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use App\Services\AccountDeletionService;
use Illuminate\Http\Request;

/** Member app: delete my account (store requirement). */
class AccountController extends Controller
{
    public function destroy(Request $r, AccountDeletionService $svc)
    {
        $d = $r->validate(['confirm' => 'required|in:DELETE', 'reason' => 'nullable|string|max:300']);
        $svc->request($r->user(), $d['reason'] ?? null);
        return ['ok' => true, 'message' => 'Your account has been marked for deletion. You have been logged out. ' . AccountDeletionService::GRACE_DAYS . ' days from now your personal data will be permanently removed. To restore the account, contact the Mahasabha office before then.'];
    }
}
