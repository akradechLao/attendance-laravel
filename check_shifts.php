<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->handle(new \Symfony\Component\Console\Input\StringInput(''), new \Symfony\Component\Console\Output\NullOutput());

$es = \App\Models\Employee::where('company_id',1)->with('shifts')->get();
foreach($es as $e){
    $times = $e->shifts->map(function($s){
        return $s->group_type.'('.$s->start_time.'-'.$s->end_time.')';
    })->implode(', ');
    echo $e->employee_code.' '.$e->name.' | '.($times ?: 'no shift').PHP_EOL;
}
