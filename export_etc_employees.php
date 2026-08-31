<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Employee;

$ets = Employee::where('company_id', 2)
    ->where('is_active', true)
    ->select('employee_code', 'name', 'office_location_id')
    ->orderBy('employee_code')
    ->get();

echo "Total ETC: " . $ets->count() . PHP_EOL;
$hasLoc = $ets->filter(fn($e) => $e->office_location_id > 0);
$noLoc = $ets->filter(fn($e) => !$e->office_location_id);
echo "Has location: " . $hasLoc->count() . PHP_EOL;
echo "No location: " . $noLoc->count() . PHP_EOL;

echo PHP_EOL . "--- Has Location ---" . PHP_EOL;
foreach ($hasLoc as $e) {
    echo $e->employee_code . '|' . $e->name . '|loc=' . $e->office_location_id . PHP_EOL;
}

echo PHP_EOL . "--- No Location ---" . PHP_EOL;
foreach ($noLoc as $e) {
    echo $e->employee_code . '|' . $e->name . PHP_EOL;
}
