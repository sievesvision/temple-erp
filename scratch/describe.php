<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$allTables = DB::select("SHOW TABLES");
$dbName = DB::connection()->getDatabaseName();
$prop = "Tables_in_" . $dbName;
foreach ($allTables as $tObj) {
    $table = $tObj->$prop;
    echo "=== $table ===\n";
    try {
        $cols = DB::select("DESCRIBE `$table`");
        foreach ($cols as $col) {
            echo "  {$col->Field} - {$col->Type} - Null: {$col->Null} - Key: {$col->Key} - Default: {$col->Default}\n";
        }
    } catch (\Exception $e) {
        echo "  Error: " . $e->getMessage() . "\n";
    }
}
