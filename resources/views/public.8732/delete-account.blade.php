@extends('layouts.public')
@section('title','Delete your account')
@section('content')
<section class="max-w-2xl mx-auto px-4 py-10">
 <h1 class="font-display text-3xl mb-4">Delete your ABMM Sampark account</h1>
 <p class="mb-3">You can delete your account yourself from the app: <b>Profile &rarr; Settings &rarr; Delete my account</b>, then type <code>DELETE</code> to confirm.</p>
 <p class="mb-3">If you cannot open the app, write to the Mahasabha office from your registered mobile number / e-mail (see the Contact page) and ask for deletion.</p>
 <h2 class="font-semibold mt-6 mb-2">What happens</h2>
 <ul class="list-disc pl-6 space-y-1">
  <li>You are logged out at once and your profile is hidden from every directory.</li>
  <li>After <b>{{ \App\Services\AccountDeletionService::GRACE_DAYS }} days</b> your name, mobile, e-mail, photo, date of birth, address, Aadhaar and your profiles (matrimony, jobs, blood donor, mentor, learner, investor) are permanently erased. Until then the office can restore the account if you change your mind.</li>
  <li>Payment records, receipts and 80G donation records are kept as required by tax law, but are no longer linked to your name.</li>
  <li>Other members of your family keep their own accounts and records.</li>
 </ul>
</section>
@endsection
