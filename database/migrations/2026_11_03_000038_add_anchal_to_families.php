<?php

use App\Models\{Family, Lookup};
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Store the ABMM Anchal of each family (derived from its ABMM Pradesh) instead of deriving it on every read. */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('families', function (Blueprint $t) {
            $t->string('anchal', 120)->nullable()->after('pradesh');
        });

        // Backfill existing families from their pradesh.
        Family::withoutGlobalScopes()->whereNotNull('pradesh')->where('pradesh', '!=', '')
            ->select('pradesh')->distinct()->pluck('pradesh')
            ->each(function ($pradesh) {
                $anchal = Lookup::anchalOf($pradesh);
                if ($anchal) Family::withoutGlobalScopes()->where('pradesh', $pradesh)->update(['anchal' => $anchal]);
            });
    }

    public function down(): void
    {
        Schema::table('families', function (Blueprint $t) {
            $t->dropColumn('anchal');
        });
    }
};
