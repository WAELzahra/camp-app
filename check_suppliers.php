<?php
define('LARAVEL_START', microtime(true));
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

// Recent events + their group's accepted supplier
$rows = DB::select("
    SELECT e.id, e.title, e.group_id,
           l.id as link_id, l.status as link_status,
           u.first_name, u.last_name
    FROM events e
    LEFT JOIN organizer_supplier_links l
           ON l.organizer_id = e.group_id AND l.status = 'accepted'
    LEFT JOIN users u ON u.id = l.supplier_id
    ORDER BY e.id DESC
    LIMIT 5
");

echo "=== Recent events + accepted supplier ===\n";
foreach ($rows as $r) {
    $sup = $r->first_name ? "{$r->first_name} {$r->last_name}" : 'NONE';
    echo "Event#{$r->id} (group={$r->group_id}) \"{$r->title}\" => supplier: $sup\n";
}

echo "\n=== All organizer_supplier_links ===\n";
$links = DB::select("SELECT l.*, u.first_name as org_name, s.first_name as sup_name FROM organizer_supplier_links l JOIN users u ON u.id=l.organizer_id JOIN users s ON s.id=l.supplier_id ORDER BY l.id DESC LIMIT 10");
foreach ($links as $l) {
    echo "Link#{$l->id} org={$l->org_name}(#{$l->organizer_id}) -> sup={$l->sup_name}(#{$l->supplier_id}) status={$l->status}\n";
}
