<?php

namespace App\Services;

use App\Models\{Plan, Subscription, User};
use Illuminate\Support\Facades\DB;

/**
 * Apple In-App Purchase (StoreKit 2) verification WITHOUT any SDK.
 * The iPhone app sends the signed transaction (a JWS). We check: ES256 signature of the leaf certificate in the x5c header, that the
 * certificate chain ends at Apple Root CA G3 (file: abmm.ios.root_ca), bundle id, product mapping, environment, replay (transaction id unique).
 * Also handles App Store Server Notifications V2 (renewal / expiry / refund).
 */
class AppleIap
{
    public const FALLBACK_PURPOSES = ['course', 'housie', 'jobpack'];

    private function b64(string $s): string { return base64_decode(strtr($s, '-_', '+/') . str_repeat('=', (4 - strlen($s) % 4) % 4)); }

    private function pem(string $der64): string { return "-----BEGIN CERTIFICATE-----\n" . chunk_split($der64, 64, "\n") . "-----END CERTIFICATE-----\n"; }

    /** JOSE r||s signature -> ASN.1 DER for openssl_verify */
    private function joseToDer(string $sig): ?string
    {
        if (strlen($sig) !== 64) return null;
        $int = function (string $x) { $x = ltrim($x, "\x00"); if ($x === '' || ord($x[0]) > 127) $x = "\x00" . $x; return "\x02" . chr(strlen($x)) . $x; };
        $body = $int(substr($sig, 0, 32)) . $int(substr($sig, 32));
        return "\x30" . chr(strlen($body)) . $body;
    }

    /** @return array<string,mixed>|null the verified payload, or null when anything does not check out */
    public function verifyJws(string $jws): ?array
    {
        $p = explode('.', $jws); if (count($p) !== 3) return null;
        $h = json_decode($this->b64($p[0]), true); $c = json_decode($this->b64($p[1]), true);
        if (! is_array($h) || ! is_array($c) || ($h['alg'] ?? '') !== 'ES256' || empty($h['x5c']) || ! is_array($h['x5c']) || count($h['x5c']) < 2) return null;
        $root = (string) config('abmm.ios.root_ca');
        if (! $root || ! is_file($root)) return null;
        $leaf = $this->pem($h['x5c'][0]);
        $inter = implode('', array_map(fn ($x) => $this->pem($x), array_slice($h['x5c'], 1)));
        $tmp = tempnam(sys_get_temp_dir(), 'iap'); file_put_contents($tmp, $inter);
        try { $chainOk = openssl_x509_checkpurpose($leaf, X509_PURPOSE_ANY, [$root], $tmp) === true; } finally { @unlink($tmp); }
        if (! $chainOk) return null;
        $key = openssl_pkey_get_public($leaf); $der = $this->joseToDer($this->b64($p[2]));
        if (! $key || ! $der || openssl_verify($p[0] . '.' . $p[1], $der, $key, OPENSSL_ALGO_SHA256) !== 1) return null;
        return $c;
    }

    private function envOk(?string $env): bool { return $env === 'Production' || ($env === 'Sandbox' && config('abmm.ios.allow_sandbox')); }

    /** Member app: "I bought product X" -> verified -> access granted. Returns ['ok'=>bool,'code'=>..,'message'=>..]. */
    public function redeem(User $u, string $signedTransaction): array
    {
        $t = $this->verifyJws($signedTransaction);
        if (! $t) return $this->no('invalid', 'The purchase could not be verified.');
        if (($t['bundleId'] ?? null) !== config('abmm.ios.bundle_id')) return $this->no('bundle', 'This purchase does not belong to this app.');
        if (! $this->envOk($t['environment'] ?? null)) return $this->no('environment', 'Sandbox purchases are not allowed in production.');
        if (! empty($t['revocationDate'])) return $this->no('revoked', 'This purchase has been refunded / revoked.');
        $tid = (string) ($t['transactionId'] ?? ''); $otid = (string) ($t['originalTransactionId'] ?? $tid);
        $prod = DB::table('iap_products')->where(['product_id' => $t['productId'] ?? '', 'is_active' => true])->first();
        if (! $tid || ! $prod) return $this->no('product', 'This product is not configured.');
        $expires = ! empty($t['expiresDate']) ? \Carbon\Carbon::createFromTimestampMs((int) $t['expiresDate']) : null;
        if ($expires && $expires->isPast()) return $this->no('expired', 'This subscription has expired.');
        if ($old = DB::table('iap_transactions')->where('transaction_id', $tid)->first()) {
            return $old->user_id === $u->id ? ['ok' => true, 'code' => 'already', 'message' => 'Already activated.'] : $this->no('owner', 'This purchase is linked to another account.');
        }
        if ($orig = DB::table('iap_transactions')->where('original_transaction_id', $otid)->where('user_id', '!=', $u->id)->exists()) return $this->no('owner', 'This purchase is linked to another account.');
        return DB::transaction(function () use ($u, $t, $tid, $otid, $prod, $expires) {
            $subId = null;
            if ($prod->kind === 'course') {
                $c = DB::table('courses')->find($prod->ref_id); if (! $c) return $this->no('product', 'Course not found.');
                $had = DB::table('course_enrolments')->where(['course_id' => $c->id, 'user_id' => $u->id])->first();
                if ($had) DB::table('course_enrolments')->where('id', $had->id)->update(['status' => 'active', 'source' => 'purchase', 'updated_at' => now()]);
                else DB::table('course_enrolments')->insert(['course_id' => $c->id, 'user_id' => $u->id, 'source' => 'purchase', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
                if (! $had || $had->status !== 'active') DB::table('courses')->where('id', $c->id)->increment('enrolments');
            } else {
                $plan = Plan::find($prod->ref_id); if (! $plan) return $this->no('product', 'Plan not found.');
                $months = $plan->months();
                $ends = $expires?->toDateString() ?? ($months ? today()->addMonthsNoOverflow($months)->subDay()->toDateString() : null);
                $s = Subscription::create(['user_id' => $u->id, 'family_id' => $u->family_id, 'subscriber_type' => $plan->audience, 'plan_id' => $plan->id, 'status' => 'active', 'starts_on' => today(), 'ends_on' => $ends, 'auto_renew' => (bool) $expires]);
                $subId = $s->id;
            }
            DB::table('iap_transactions')->insert(['transaction_id' => $tid, 'original_transaction_id' => $otid, 'user_id' => $u->id, 'family_id' => $u->family_id, 'product_id' => $prod->product_id, 'kind' => $prod->kind, 'ref_id' => $prod->ref_id,
                'environment' => $t['environment'] ?? 'Production', 'purchased_at' => ! empty($t['purchaseDate']) ? \Carbon\Carbon::createFromTimestampMs((int) $t['purchaseDate']) : now(), 'expires_at' => $expires, 'status' => 'active', 'subscription_id' => $subId, 'created_at' => now(), 'updated_at' => now()]);
            return ['ok' => true, 'code' => 'granted', 'message' => 'Purchase activated.'];
        });
    }

    /** App Store Server Notification V2 (POST body {"signedPayload": "..."}). Returns true when understood and applied (or ignored safely). */
    public function notification(string $signedPayload): bool
    {
        $n = $this->verifyJws($signedPayload); if (! $n) return false;
        $type = (string) ($n['notificationType'] ?? ''); $data = $n['data'] ?? [];
        $info = ! empty($data['signedTransactionInfo']) ? $this->verifyJws($data['signedTransactionInfo']) : null;
        if (! $info) return true;                                         // nothing we can act on (e.g. TEST ping)
        $row = DB::table('iap_transactions')->where('original_transaction_id', (string) ($info['originalTransactionId'] ?? ''))->orderByDesc('id')->first();
        if (! $row) return true;
        if (in_array($type, ['REFUND', 'REVOKE'], true)) $this->end($row, 'refunded');
        elseif ($type === 'EXPIRED' || ($type === 'DID_CHANGE_RENEWAL_STATUS' && false)) $this->end($row, 'expired');
        elseif ($type === 'DID_RENEW' && ! empty($info['expiresDate'])) {
            $exp = \Carbon\Carbon::createFromTimestampMs((int) $info['expiresDate']);
            DB::table('iap_transactions')->where('id', $row->id)->update(['expires_at' => $exp, 'status' => 'active', 'updated_at' => now()]);
            if ($row->subscription_id) Subscription::whereKey($row->subscription_id)->update(['status' => 'active', 'ends_on' => $exp->toDateString()]);
        }
        return true;
    }

    private function end(object $row, string $status): void
    {
        DB::table('iap_transactions')->where('id', $row->id)->update(['status' => $status, 'updated_at' => now()]);
        if ($row->subscription_id) Subscription::whereKey($row->subscription_id)->update(['status' => $status === 'refunded' ? 'cancelled' : 'expired', 'auto_renew' => false]);
        if ($row->kind === 'course' && $status === 'refunded') {
            DB::table('course_enrolments')->where(['course_id' => $row->ref_id, 'user_id' => $row->user_id])->update(['status' => 'cancelled', 'updated_at' => now()]);
            DB::table('courses')->where('id', $row->ref_id)->update(['enrolments' => DB::raw('case when enrolments > 0 then enrolments - 1 else 0 end')]);
        }
    }

    private function no(string $code, string $msg): array { return ['ok' => false, 'code' => $code, 'message' => $msg]; }
}
