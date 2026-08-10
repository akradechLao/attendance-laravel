<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->handle(new \Symfony\Component\Console\Input\StringInput(''), new \Symfony\Component\Console\Output\NullOutput());

use App\Models\OfficeLocation;
use App\Models\Employee;

echo "=== Creating ETECH location ===\n";
$etech = OfficeLocation::create([
    'company_id' => 3,
    'name' => 'ETECH',
    'address' => 'ETECH',
    'latitude' => 13.095645,
    'longitude' => 100.963970,
    'radius_meters' => 200,
    'work_start_time' => '08:00',
    'work_end_time' => '17:00',
    'is_active' => true,
]);
echo "ETECH location created: ID=".$etech->id."\n";

$etechEmployees = ['ทัชชา', 'ธิติรัตน์', 'ณิชาภา', 'นิตติยา', 'วารุณี', 'เอนก'];
foreach ($etechEmployees as $name) {
    $emp = Employee::where('company_id', 3)->where('name', 'LIKE', '%'.$name.'%')->first();
    if ($emp) {
        $etech->assignedEmployees()->attach($emp->id);
        echo "  Assigned: ".$emp->employee_code." ".$emp->name."\n";
    } else {
        echo "  NOT FOUND: ".$name."\n";
    }
}

echo "\n=== Creating NTC location ===\n";
$ntc = OfficeLocation::create([
    'company_id' => 1,
    'name' => 'NTC',
    'address' => 'NTC (temp - same as ETECH)',
    'latitude' => 13.095645,
    'longitude' => 100.963970,
    'radius_meters' => 200,
    'work_start_time' => '08:00',
    'work_end_time' => '17:00',
    'is_active' => true,
]);
echo "NTC location created: ID=".$ntc->id."\n";

$ntcEmployees = Employee::where('company_id', 1)->get();
foreach ($ntcEmployees as $emp) {
    $ntc->assignedEmployees()->attach($emp->id);
    echo "  Assigned: ".$emp->employee_code." ".$emp->name."\n";
}

echo "\n=== Done ===\n";
echo "ETech: ".$etech->assignedEmployees()->count()." employees\n";
echo "NTC: ".$ntc->assignedEmployees()->count()." employees\n";
