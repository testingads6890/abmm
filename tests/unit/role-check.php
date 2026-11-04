<?php

// Read-only: which roles can use the changed features (CSV export and the family search).
// Usage: php tests/unit/role-check.php

require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;

foreach (['super_admin', 'state_admin', 'district_operator'] as $role) {
    $u = User::where('role', $role)->first();
    if (! $u) { echo "$role: no user\n"; continue; }
    printf("%-18s families.view=%s families.export=%s reports.view=%s tehsil_area=%s\n", $role,
        json_encode($u->can('families.view')), json_encode($u->can('families.export')), json_encode($u->can('reports.view')),
        $u->pradesh ?: ($u->isSuper() ? 'all' : 'none'));
}
