<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Department, Lookup, Trust, TrustSubmission};
use App\Services\RazorpayService;
use App\Support\{CustomForm, Exporter};
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Mail;

/** Super Admin: trust directory (state / district wise), PDF download, and online submission (+ optional Razorpay fee) to a department. */
class TrustController extends Controller
{
    private function masterOpts(string $type): array
    {
        return Lookup::grouped()[$type] ?? [];
    }

    private function rules(Request $r): array
    {
        $catId = Lookup::ofType('trust_category')->active()->where('name', (string) $r->input('trust_category'))->value('id');
        return [
            'name' => 'required|max:200', 'email' => 'required|email|max:160', 'dob' => 'nullable|date|before:today', 'contact' => 'required|digits:10',
            'gender' => ['nullable', Rule::in($this->masterOpts('gender'))], 'marital_status' => ['nullable', Rule::in($this->masterOpts('marital_status'))],
            'city' => 'nullable|max:100', 'aadhar' => 'nullable|digits:12',
            'trust_category' => ['required', Rule::in($this->masterOpts('trust_category'))],
            'trust_name' => ['required', 'max:200', fn ($a, $v, $fail) => Lookup::ofType('trust_name')->active()->where('name', $v)->where('parent_id', $catId)->exists() ?: $fail('This Trust is not in this Category.')],
            'address' => 'nullable|max:600', 'country' => 'nullable|max:60', 'state' => 'nullable|max:80', 'district' => 'nullable|max:80', 'pincode' => 'nullable|digits:6',
        ];
    }

    private function query(Request $r)
    {
        $in = fn (string $k) => array_values(array_filter((array) $r->input($k)));
        $q = Trust::query()->withCount(['submissions as submitted_count' => fn ($s) => $s->where('status', 'submitted')]);
        foreach (['trust_category', 'trust_name', 'gender', 'marital_status', 'state', 'district'] as $k) if ($v = $in($k)) $q->whereIn($k, $v);
        return $q->when($r->q, fn ($q, $t) => $q->where(fn ($w) => $w->where('name', 'like', "%$t%")->orWhere('email', 'like', "%$t%")->orWhere('contact', 'like', "%$t%")->orWhere('city', 'like', "%$t%")))
            ->orderBy('trust_category')->orderBy('trust_name')->orderBy('name');
    }

    /** state name => [district names] for the dependent dropdown. */
    private function stateDistricts(): array
    {
        $states = Lookup::where('type', 'state')->where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $dist = Lookup::where('type', 'district')->where('is_active', true)->orderBy('name')->get(['name', 'parent_id'])->groupBy('parent_id');
        return $states->unique('name')->mapWithKeys(fn ($s) => [$s->name => ($dist[$s->id] ?? collect())->pluck('name')->unique()->values()->all()])->all();
    }

    /** category name => [trust names] for the dependent dropdown. */
    private function categoryTrusts(): array
    {
        $cats = Lookup::ofType('trust_category')->active()->orderBy('sort')->orderBy('name')->get(['id', 'name']);
        $names = Lookup::ofType('trust_name')->active()->orderBy('sort')->orderBy('name')->get(['name', 'parent_id'])->groupBy('parent_id');
        return $cats->mapWithKeys(fn ($c) => [$c->name => ($names[$c->id] ?? collect())->pluck('name')->unique()->values()->all()])->all();
    }

    public function index(Request $r)
    {
        if (in_array($r->export, ['csv', 'xlsx', 'pdf'], true)) {
            $extraHeads = array_keys(CustomForm::display('trust', [], true));
            $rows = $this->query($r)->get()->map(fn ($t, $i) => array_merge([$i + 1, $t->trust_category, $t->trust_name, $t->name, $t->email, $t->dob?->format('d-M-Y'), $t->contact, $t->gender, $t->marital_status, $t->city, $t->aadharMasked(), $t->state, $t->district],
                array_values(CustomForm::display('trust', $t->extra, true))));
            return Exporter::download($r->export, 'abmm-trusts', 'Trust List', array_merge(['#', 'Category', 'Name of Trust', 'Name', 'Email ID', 'DOB', 'Mobile Number', 'Gender', 'Marital Status', 'City', 'Aadhar Card', 'Govt. State', 'District'], $extraHeads), $rows);
        }
        return view('admin.trusts.index', [
            'rows' => $this->query($r)->paginate(in_array((int) $r->per, [10, 25, 50, 100]) ? (int) $r->per : 25)->withQueryString(),
            'sd' => $this->stateDistricts(), 'ct' => $this->categoryTrusts(), 'lk' => Lookup::grouped(), 'departments' => Department::where('is_active', true)->orderBy('name')->get(),
            'razorpay' => app(RazorpayService::class)->configured(),
        ]);
    }

    public function store(Request $r)
    {
        $d = $r->validate($this->rules($r));
        $t = Trust::create($d + ['created_by' => $r->user()->id, 'extra' => CustomForm::collect($r, 'trust')]);
        return back()->with('ok', "'{$t->name}' ({$t->trust_name}) recorded.");
    }

    public function update(Request $r, Trust $trust)
    {
        $d = $r->validate($this->rules($r));
        if (empty($d['aadhar'])) unset($d['aadhar']);
        $trust->update($d + ['extra' => CustomForm::collect($r, 'trust', $trust->extra)]);
        return back()->with('ok', 'Updated.');
    }

    public function destroy(Trust $trust)
    {
        abort_if($trust->submissions()->where('status', 'submitted')->exists(), 422, 'A Trust already submitted to a department cannot be deleted.');
        $trust->delete();
        return back()->with('ok', 'Trust removed.');
    }

    public function pdf(Trust $trust)
    {
        return $this->pdfResponse($trust);
    }

    private function pdfResponse(Trust $trust)
    {
        $rows = collect($trust->details())->map(fn ($v, $k) => [$k, (string) $v])->values();
        return Exporter::download('pdf', 'trust-' . $trust->id, $trust->name, ['Field', 'Details'], $rows);
    }

    /** Super Admin picks a department. Fee = 0 -> submitted straight away; else Razorpay order, submitted after verified payment. */
    public function submit(Request $r, Trust $trust, RazorpayService $rzp)
    {
        $d = $r->validate(['department_id' => 'required|exists:departments,id']);
        $dept = Department::where('is_active', true)->findOrFail($d['department_id']);
        // per-department form: each department can define its own extra fields (Departments screen)
        $data = []; $rules = [];
        foreach (json_decode($dept->getRawOriginal('fields') ?: '[]', true) ?: [] as $f) {
            $rules['df.' . $f['key']] = [$f['required'] ? 'required' : 'nullable', $f['type'] === 'number' ? 'numeric' : ($f['type'] === 'date' ? 'date' : ($f['type'] === 'select' ? \Illuminate\Validation\Rule::in($f['options']) : 'string'))];
        }
        if ($rules) { $v = $r->validate($rules); $data = $v['df'] ?? []; }
        $sub = TrustSubmission::create(['trust_id' => $trust->id, 'department_id' => $dept->id, 'amount' => $dept->fee, 'status' => 'pending', 'submitted_by' => $r->user()->id, 'data' => $data ?: null]);
        if ((float) $dept->fee <= 0) {
            $this->deliver($sub);
            return redirect()->route('admin.trusts.submissions')->with('ok', "Trust '{$trust->name}' was submitted to {$dept->name}.");
        }
        if (! $rzp->configured()) { $sub->update(['status' => 'failed']); return back()->withErrors(['department_id' => 'Payment gateway is not configured (RAZORPAY_KEY_ID / SECRET).']); }
        try {
            $order = $rzp->createOrder($dept->fee, 'trs_' . $sub->id, ['trust_submission_id' => (string) $sub->id]);
        } catch (\Throwable $e) {
            report($e);
            $sub->update(['status' => 'failed']);
            return back()->withErrors(['department_id' => 'Payment could not be started, please try again shortly.']);
        }
        $sub->update(['razorpay_order_id' => $order['id']]);
        return view('admin.trusts.pay', ['sub' => $sub->load('trust', 'department'), 'keyId' => config('abmm.razorpay.key_id'), 'order' => $order]);
    }

    public function verify(Request $r, RazorpayService $rzp)
    {
        $d = $r->validate(['razorpay_order_id' => 'required', 'razorpay_payment_id' => 'required', 'razorpay_signature' => 'required']);
        $sub = TrustSubmission::where('razorpay_order_id', $d['razorpay_order_id'])->firstOrFail();
        if (! $rzp->verifyPayment($d['razorpay_order_id'], $d['razorpay_payment_id'], $d['razorpay_signature'])) {
            return redirect()->route('admin.trusts.submissions')->withErrors(['payment' => 'Payment could not be verified.']);
        }
        $this->markPaid($sub, $d['razorpay_payment_id']);
        return redirect()->route('admin.trusts.submissions')->with('ok', 'Payment received and the trust was submitted to the department.');
    }

    /** Called from verify() and from the Razorpay webhook (idempotent). */
    public function markPaid(TrustSubmission $sub, string $paymentId): void
    {
        if ($sub->status === 'submitted') return;
        $sub->update(['razorpay_payment_id' => $paymentId, 'paid_at' => now()]);
        $this->deliver($sub);
    }

    /** Marks as submitted and e-mails the department (trust details + PDF). A mail failure is recorded, never lost. */
    private function deliver(TrustSubmission $sub): void
    {
        $sub->load('trust', 'department');
        $sub->update(['status' => 'submitted', 'submitted_at' => now()]);
        $t = $sub->trust;
        try {
            $pdf = $this->pdfResponse($t)->getContent();
            $body = "Trust submission #{$sub->id}\n\n" . collect($t->details())->map(fn ($v, $k) => "$k: $v")->implode("\n")
                . ($sub->data ? "\n\nDepartment form:\n" . collect($sub->data)->map(fn ($v, $k) => preg_replace('/^f\d+_/', '', $k) . ': ' . $v)->implode("\n") : '')
                . ($sub->amount > 0 ? "\n\nFee paid: Rs. {$sub->amount} (Razorpay: {$sub->razorpay_payment_id})" : '');
            Mail::raw($body, function ($m) use ($sub, $t, $pdf) {
                $m->to($sub->department->email)->subject("Trust submission: {$t->trust_name} - {$t->name}")->attachData($pdf, 'trust-' . $t->id . '.pdf', ['mime' => 'application/pdf']);
            });
        } catch (\Throwable $e) {
            report($e);
            $sub->update(['mail_error' => mb_substr($e->getMessage(), 0, 500)]);
        }
    }

    public function resend(TrustSubmission $submission)
    {
        abort_unless($submission->status === 'submitted', 404);
        $submission->update(['mail_error' => null]);
        $this->deliver($submission);
        return back()->with('ok', $submission->fresh()->mail_error ? 'Mail still failed: ' . $submission->fresh()->mail_error : 'Sent to the department again.');
    }

    public function submissions(Request $r)
    {
        $rows = TrustSubmission::with('trust', 'department')->when($r->status, fn ($q, $v) => $q->where('status', $v))->latest()->paginate(25)->withQueryString();
        return view('admin.trusts.submissions', compact('rows'));
    }

    /* ---------------- departments ---------------- */
    public function departments()
    {
        return view('admin.trusts.departments', ['rows' => Department::orderBy('name')->get()]);
    }

    public function departmentStore(Request $r)
    {
        Department::create($r->validate(['name' => 'required|max:160|unique:departments,name', 'email' => 'required|email|max:160', 'fee' => 'required|numeric|min:0|max:500000']));
        return back()->with('ok', 'Department added.');
    }

    public function departmentUpdate(Request $r, Department $department)
    {
        $department->update($r->validate(['name' => 'required|max:160|unique:departments,name,' . $department->id, 'email' => 'required|email|max:160', 'fee' => 'required|numeric|min:0|max:500000']) + ['is_active' => $r->boolean('is_active')]);
        return back()->with('ok', 'Department updated.');
    }

    public function departmentDestroy(Department $department)
    {
        abort_if(TrustSubmission::where('department_id', $department->id)->exists(), 422, 'A department with submissions cannot be deleted; make it inactive.');
        $department->delete();
        return back()->with('ok', 'Department removed.');
    }
}
