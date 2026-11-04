<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/** Milestone C: member card & benefits, networking & chat, directories (bhavan/hotel/hospital/clinic/doctor), business categories & organisations, ad packages, B2B marketplace, business groups, ticketing, online events, event vendors, department forms. */
return new class extends Migration {
    public function up(): void
    {
        // ---- member card, categories, benefits
        Schema::create('membership_categories', function (Blueprint $t) {
            $t->id(); $t->string('code', 30)->unique(); $t->string('name', 80); $t->unsignedBigInteger('plan_id')->nullable(); $t->string('color', 9)->default('#B8860B');
            $t->text('perks')->nullable(); $t->unsignedSmallInteger('sort')->default(0); $t->boolean('is_active')->default(true); $t->timestamps();
        });
        Schema::create('benefits', function (Blueprint $t) {
            $t->id(); $t->string('title', 150); $t->text('description')->nullable(); $t->unsignedBigInteger('business_id')->nullable(); $t->string('kind', 12)->default('voucher'); // voucher|discount|service
            $t->string('discount_text', 80)->nullable(); $t->json('eligibility')->nullable();      // {categories:[codes], countries:[], cities:[], min_points:n, verified_only:bool}
            $t->unsignedSmallInteger('per_member_limit')->default(1); $t->unsignedInteger('total_limit')->nullable(); $t->unsignedInteger('claimed_count')->default(0);
            $t->date('valid_from')->nullable(); $t->date('valid_to')->nullable(); $t->boolean('is_active')->default(true); $t->unsignedBigInteger('created_by')->nullable(); $t->timestamps();
        });
        Schema::create('benefit_claims', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('benefit_id')->index(); $t->unsignedBigInteger('user_id')->index(); $t->unsignedBigInteger('family_id')->nullable();
            $t->string('country', 100)->nullable(); $t->string('city', 150)->nullable(); $t->string('code', 14)->unique(); $t->string('status', 10)->default('claimed'); // claimed|redeemed|void
            $t->timestamp('redeemed_at')->nullable(); $t->unsignedBigInteger('redeemed_by')->nullable(); $t->timestamps();
        });

        // ---- networking
        Schema::create('connections', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('user_id'); $t->unsignedBigInteger('target_id'); $t->string('status', 10)->default('pending'); // pending|accepted|rejected|blocked
            $t->string('note', 200)->nullable(); $t->timestamps(); $t->unique(['user_id', 'target_id']); $t->index('target_id');
        });
        Schema::create('member_reports', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('reporter_id'); $t->unsignedBigInteger('target_id'); $t->string('reason', 40); $t->string('detail', 500)->nullable();
            $t->string('status', 10)->default('open'); $t->unsignedBigInteger('handled_by')->nullable(); $t->timestamps(); $t->index('target_id');
        });
        Schema::create('profile_views', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('viewer_id'); $t->unsignedBigInteger('target_id'); $t->timestamp('viewed_at')->useCurrent(); $t->index(['viewer_id', 'viewed_at']); $t->index('target_id');
        });
        Schema::create('saved_members', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('user_id'); $t->unsignedBigInteger('target_id'); $t->timestamp('created_at')->useCurrent(); $t->unique(['user_id', 'target_id']);
        });
        Schema::create('networking_settings', function (Blueprint $t) {
            $t->unsignedBigInteger('user_id')->primary(); $t->boolean('discoverable')->default(true); $t->boolean('auto_accept')->default(false); $t->boolean('contact_sync')->default(false);
            $t->string('phone_hash', 64)->nullable()->index(); $t->timestamp('consent_at')->nullable(); $t->timestamps();
        });
        Schema::create('chat_messages', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('connection_id')->index(); $t->unsignedBigInteger('sender_id'); $t->text('body'); $t->timestamp('read_at')->nullable(); $t->boolean('hidden')->default(false); $t->timestamp('created_at')->useCurrent();
        });
        Schema::create('message_reports', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('message_id'); $t->unsignedBigInteger('reporter_id'); $t->string('reason', 80); $t->string('status', 10)->default('open'); $t->timestamps();
        });

        // ---- directories: bhavan / dharamshala / hotel / hospital / clinic / doctor
        Schema::create('directory_entries', function (Blueprint $t) {
            $t->id(); $t->string('kind', 12)->index(); $t->string('name', 180); $t->unsignedBigInteger('parent_id')->nullable(); $t->string('speciality', 120)->nullable();
            $t->string('country', 100)->nullable()->index(); $t->string('state', 120)->nullable(); $t->string('city', 150)->nullable()->index(); $t->string('zone', 100)->nullable();
            $t->string('address', 400)->nullable(); $t->string('phone', 30)->nullable(); $t->string('email', 150)->nullable(); $t->string('website', 200)->nullable(); $t->text('description')->nullable();
            $t->json('facilities')->nullable(); $t->string('rate_note', 200)->nullable(); $t->unsignedTinyInteger('member_discount_pct')->default(0); $t->boolean('verified')->default(false); $t->string('verification_note', 200)->nullable();
            $t->unsignedBigInteger('owner_user_id')->nullable(); $t->string('status', 10)->default('pending'); $t->date('listing_until')->nullable(); $t->string('image', 200)->nullable(); $t->unsignedInteger('views')->default(0); $t->timestamps();
        });
        Schema::create('directory_rooms', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('entry_id')->index(); $t->string('name', 120); $t->string('kind', 10)->default('room'); $t->unsignedSmallInteger('capacity')->default(0);
            $t->decimal('rate', 10, 2)->default(0); $t->string('currency', 3)->default('INR'); $t->boolean('is_active')->default(true);
        });
        Schema::create('directory_bookings', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('entry_id')->index(); $t->unsignedBigInteger('room_id')->nullable(); $t->unsignedBigInteger('user_id'); $t->unsignedBigInteger('family_id')->nullable();
            $t->date('from_date'); $t->date('to_date'); $t->unsignedSmallInteger('guests')->default(1); $t->string('purpose', 150)->nullable(); $t->string('status', 10)->default('enquiry'); // enquiry|confirmed|rejected|cancelled
            $t->string('response', 250)->nullable(); $t->timestamps(); $t->index(['room_id', 'from_date']);
        });

        // ---- business categories, organisations, listings
        Schema::create('business_categories', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('parent_id')->nullable()->index(); $t->string('name', 120); $t->string('slug', 140)->unique(); $t->unsignedSmallInteger('sort')->default(0); $t->boolean('is_active')->default(true);
        });
        Schema::table('businesses', function (Blueprint $t) {
            $t->unsignedBigInteger('category_id')->nullable()->index(); $t->string('org_kind', 12)->default('business'); // business | profit | nonprofit
            $t->string('reg_no', 60)->nullable(); $t->boolean('has_12a')->default(false); $t->boolean('has_80g')->default(false);
            $t->string('country', 100)->nullable(); $t->string('city', 150)->nullable(); $t->string('zone', 100)->nullable();
            $t->date('listing_until')->nullable(); $t->boolean('seller_verified')->default(false); $t->timestamp('seller_verified_at')->nullable(); $t->unsignedInteger('views')->default(0); $t->boolean('is_featured')->default(false);
        });

        // ---- ads
        Schema::create('ad_packages', function (Blueprint $t) {
            $t->id(); $t->string('name', 100); $t->string('kind', 14); // featured|sponsored|banner|category_top|push
            $t->decimal('price', 10, 2); $t->string('currency', 3)->default('INR'); $t->unsignedSmallInteger('days')->default(30); $t->unsignedInteger('impression_cap')->nullable();
            $t->boolean('geo_targeting')->default(false); $t->text('description')->nullable(); $t->boolean('is_active')->default(true); $t->unsignedSmallInteger('sort')->default(0); $t->timestamps();
        });
        Schema::table('business_ads', function (Blueprint $t) {
            $t->unsignedBigInteger('package_id')->nullable(); $t->string('kind', 14)->default('banner'); $t->json('target')->nullable(); $t->unsignedBigInteger('category_id')->nullable();
            $t->unsignedInteger('impressions')->default(0); $t->unsignedInteger('clicks')->default(0); $t->unsignedInteger('impression_cap')->nullable(); $t->unsignedBigInteger('payment_id')->nullable(); $t->string('link_url', 250)->nullable();
            $t->string('push_text', 200)->nullable(); $t->timestamp('push_sent_at')->nullable(); $t->unsignedBigInteger('user_id')->nullable();
        });
        Schema::create('ad_stats', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('ad_id'); $t->date('day'); $t->unsignedInteger('impressions')->default(0); $t->unsignedInteger('clicks')->default(0); $t->unique(['ad_id', 'day']);
        });

        // ---- B2B marketplace + business groups + referrals
        Schema::create('products', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('business_id')->index(); $t->string('title', 180); $t->text('description')->nullable(); $t->unsignedBigInteger('category_id')->nullable();
            $t->decimal('price', 12, 2)->nullable(); $t->string('currency', 3)->default('INR'); $t->string('unit', 20)->nullable(); $t->unsignedInteger('moq')->default(1); $t->json('bulk_tiers')->nullable(); // [{min:100,price:90}]
            $t->string('image', 200)->nullable(); $t->unsignedInteger('views')->default(0); $t->string('status', 10)->default('pending'); $t->timestamps();
        });
        Schema::create('quotations', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('product_id')->nullable(); $t->unsignedBigInteger('business_id')->index(); $t->unsignedBigInteger('buyer_user_id')->index(); $t->unsignedBigInteger('buyer_family_id')->nullable();
            $t->unsignedInteger('qty')->default(1); $t->text('message')->nullable(); $t->string('status', 10)->default('requested'); // requested|quoted|accepted|rejected|closed
            $t->decimal('quoted_price', 12, 2)->nullable(); $t->string('response', 400)->nullable(); $t->date('valid_until')->nullable(); $t->timestamps();
        });
        Schema::create('biz_groups', function (Blueprint $t) {
            $t->id(); $t->string('name', 150); $t->text('description')->nullable(); $t->string('country', 100)->nullable(); $t->string('city', 150)->nullable(); $t->unsignedBigInteger('category_id')->nullable();
            $t->unsignedBigInteger('owner_user_id'); $t->boolean('is_active')->default(true); $t->timestamps();
        });
        Schema::create('biz_group_members', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('group_id'); $t->unsignedBigInteger('user_id'); $t->unsignedBigInteger('business_id')->nullable(); $t->string('role', 10)->default('member'); $t->timestamps(); $t->unique(['group_id', 'user_id']);
        });
        Schema::create('biz_meetings', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('group_id')->index(); $t->string('title', 150); $t->dateTime('starts_at'); $t->string('venue', 200)->nullable(); $t->string('online_url', 250)->nullable(); $t->text('note')->nullable();
            $t->unsignedBigInteger('created_by'); $t->timestamps();
        });
        Schema::create('biz_meeting_rsvps', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('meeting_id'); $t->unsignedBigInteger('user_id'); $t->string('status', 8)->default('going'); $t->unique(['meeting_id', 'user_id']);
        });
        Schema::create('biz_referrals', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('group_id')->nullable(); $t->unsignedBigInteger('from_user_id')->index(); $t->unsignedBigInteger('to_user_id')->index(); $t->unsignedBigInteger('business_id')->nullable();
            $t->string('description', 400); $t->decimal('expected_value', 12, 2)->nullable(); $t->string('status', 10)->default('given'); // given|contacted|won|lost
            $t->decimal('closed_value', 12, 2)->nullable(); $t->timestamps();
        });

        // ---- events: ticketing, online, feedback, photos
        Schema::table('events', function (Blueprint $t) {
            $t->boolean('ticketing')->default(false); $t->string('online_provider', 10)->nullable(); $t->string('online_url', 300)->nullable();
            $t->timestamp('reminded_24h_at')->nullable(); $t->timestamp('reminded_1h_at')->nullable();
        });
        Schema::create('ticket_types', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('event_id')->index(); $t->string('name', 100); $t->decimal('price', 10, 2)->default(0); $t->string('currency', 3)->default('INR');
            $t->unsignedInteger('quantity')->nullable(); $t->unsignedTinyInteger('max_per_order')->default(10); $t->dateTime('sale_starts')->nullable(); $t->dateTime('sale_ends')->nullable(); $t->boolean('is_active')->default(true);
        });
        Schema::create('ticket_orders', function (Blueprint $t) {
            $t->id(); $t->string('number', 20)->unique(); $t->unsignedBigInteger('event_id')->index(); $t->unsignedBigInteger('user_id')->index(); $t->unsignedBigInteger('family_id')->nullable();
            $t->unsignedBigInteger('ticket_type_id'); $t->unsignedSmallInteger('qty'); $t->decimal('amount', 10, 2)->default(0); $t->string('currency', 3)->default('INR'); $t->unsignedBigInteger('payment_id')->nullable();
            $t->string('coupon', 30)->nullable(); $t->string('status', 10)->default('pending'); // pending|paid|cancelled|refunded
            $t->json('attendees')->nullable(); $t->timestamps();
        });
        Schema::create('tickets', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('order_id')->index(); $t->unsignedBigInteger('event_id')->index(); $t->string('holder_name', 120); $t->string('code', 16)->unique();
            $t->timestamp('checked_in_at')->nullable(); $t->unsignedBigInteger('checked_by')->nullable(); $t->boolean('void')->default(false);
        });
        Schema::create('ticket_waitlist', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('event_id')->index(); $t->unsignedBigInteger('ticket_type_id'); $t->unsignedBigInteger('user_id'); $t->unsignedSmallInteger('qty')->default(1);
            $t->string('status', 10)->default('waiting'); $t->timestamp('notified_at')->nullable(); $t->timestamps(); $t->unique(['ticket_type_id', 'user_id']);
        });
        Schema::create('event_feedback', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('event_id'); $t->unsignedBigInteger('user_id'); $t->unsignedTinyInteger('rating'); $t->string('comment', 500)->nullable(); $t->timestamps(); $t->unique(['event_id', 'user_id']);
        });
        Schema::create('event_photos', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('event_id')->index(); $t->unsignedBigInteger('user_id'); $t->string('path', 200); $t->string('caption', 150)->nullable(); $t->string('status', 10)->default('pending'); $t->timestamps();
        });

        // ---- event vendors
        Schema::create('event_vendors', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('user_id')->nullable()->index(); $t->unsignedBigInteger('family_id')->nullable(); $t->string('category', 40)->index(); $t->string('name', 150);
            $t->string('contact_person', 100)->nullable(); $t->string('phone', 30)->nullable(); $t->string('email', 150)->nullable(); $t->string('country', 100)->nullable(); $t->string('city', 150)->nullable()->index(); $t->string('zone', 100)->nullable();
            $t->text('description')->nullable(); $t->json('services')->nullable(); $t->decimal('price_from', 12, 2)->nullable(); $t->string('currency', 3)->default('INR'); $t->json('gallery')->nullable();
            $t->boolean('verified')->default(false); $t->string('status', 10)->default('pending'); $t->date('top_until')->nullable(); $t->date('listing_until')->nullable();
            $t->unsignedInteger('views')->default(0); $t->decimal('rating_avg', 3, 2)->default(0); $t->unsignedInteger('rating_count')->default(0); $t->timestamps();
        });
        Schema::create('vendor_quotes', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('vendor_id')->index(); $t->unsignedBigInteger('user_id')->index(); $t->date('event_date')->nullable(); $t->string('event_type', 60)->nullable(); $t->decimal('budget', 12, 2)->nullable();
            $t->text('message')->nullable(); $t->string('status', 10)->default('new'); $t->decimal('quote_amount', 12, 2)->nullable(); $t->string('response', 400)->nullable(); $t->timestamps();
        });
        Schema::create('vendor_reviews', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('vendor_id'); $t->unsignedBigInteger('user_id'); $t->unsignedTinyInteger('rating'); $t->string('comment', 500)->nullable(); $t->string('status', 10)->default('approved'); $t->timestamps(); $t->unique(['vendor_id', 'user_id']);
        });

        // ---- per-department trust forms
        Schema::table('departments', function (Blueprint $t) { $t->json('fields')->nullable(); });
        Schema::table('trust_submissions', function (Blueprint $t) { $t->json('data')->nullable(); });

        // ---- seeds
        $now = now();
        foreach ([['general', 'General Member', '#6B7280', 1], ['annual', 'Annual Member', '#2563EB', 2], ['life', 'Life Member', '#B8860B', 3], ['patron', 'Patron', '#7C2D12', 4]] as [$c, $n, $col, $s])
            DB::table('membership_categories')->insert(['code' => $c, 'name' => $n, 'color' => $col, 'sort' => $s, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        foreach ([['Featured listing', 'featured', 500, 30], ['Sponsored listing', 'sponsored', 1000, 30], ['Home banner', 'banner', 2000, 30], ['Category top', 'category_top', 1500, 30], ['Push notification ad', 'push', 3000, 1]] as $i => [$n, $k, $p, $d])
            DB::table('ad_packages')->insert(['name' => $n, 'kind' => $k, 'price' => $p, 'days' => $d, 'geo_targeting' => in_array($k, ['push', 'banner'], true), 'is_active' => true, 'sort' => $i, 'created_at' => $now, 'updated_at' => $now]);
        foreach (['Food & Catering', 'Jewellery', 'Textiles & Garments', 'Real Estate', 'Education', 'Healthcare', 'IT & Services', 'Manufacturing', 'Trading & Wholesale', 'Finance & Insurance', 'Hospitality', 'Other'] as $i => $n)
            DB::table('business_categories')->insert(['name' => $n, 'slug' => \Illuminate\Support\Str::slug($n), 'sort' => $i, 'is_active' => true]);
    }

    public function down(): void
    {
        Schema::table('trust_submissions', fn (Blueprint $t) => $t->dropColumn('data'));
        Schema::table('departments', fn (Blueprint $t) => $t->dropColumn('fields'));
        foreach (['vendor_reviews', 'vendor_quotes', 'event_vendors', 'event_photos', 'event_feedback', 'ticket_waitlist', 'tickets', 'ticket_orders', 'ticket_types', 'biz_referrals', 'biz_meeting_rsvps', 'biz_meetings', 'biz_group_members', 'biz_groups',
            'quotations', 'products', 'ad_stats', 'ad_packages', 'business_categories', 'directory_bookings', 'directory_rooms', 'directory_entries', 'message_reports', 'chat_messages', 'networking_settings', 'saved_members', 'profile_views',
            'member_reports', 'connections', 'benefit_claims', 'benefits', 'membership_categories'] as $t) Schema::dropIfExists($t);
        Schema::table('events', fn (Blueprint $t) => $t->dropColumn(['ticketing', 'online_provider', 'online_url', 'reminded_24h_at', 'reminded_1h_at']));
        Schema::table('business_ads', fn (Blueprint $t) => $t->dropColumn(['package_id', 'kind', 'target', 'category_id', 'impressions', 'clicks', 'impression_cap', 'payment_id', 'link_url', 'push_text', 'push_sent_at', 'user_id']));
        Schema::table('businesses', fn (Blueprint $t) => $t->dropColumn(['category_id', 'org_kind', 'reg_no', 'has_12a', 'has_80g', 'country', 'city', 'zone', 'listing_until', 'seller_verified', 'seller_verified_at', 'views', 'is_featured']));
    }
};
