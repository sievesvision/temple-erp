<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $poojas = Illuminate\Support\Facades\DB::table('poojas')->get();
    foreach ($poojas as $pooja) {
        print_r($pooja);
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
