<?php

namespace App\Services;

use App\Models\CompanyHoliday;

class HolidayService
{
    public function getHolidays(int $companyId, int $year): array
    {
        return CompanyHoliday::where('company_id', $companyId)
            ->whereYear('date', $year)
            ->get()
            ->toArray();
    }

    public function isHoliday(int $companyId, string $date): bool
    {
        return CompanyHoliday::where('company_id', $companyId)
            ->where('date', $date)
            ->exists();
    }

    public function addHoliday(int $companyId, string $name, string $date): CompanyHoliday
    {
        return CompanyHoliday::create([
            'company_id' => $companyId,
            'name' => $name,
            'date' => $date,
        ]);
    }

    public function deleteHoliday(int $holidayId): bool
    {
        return CompanyHoliday::destroy($holidayId);
    }

    public function getThaiHolidays(int $year): array
    {
        return [
            ['name' => 'วันขึ้นปีใหม่', 'date' => "{$year}-01-01"],
            ['name' => 'วันเด็กแห่งชาติ', 'date' => "{$year}-01-08"],
            ['name' => 'วันมาฆบูชา', 'date' => "{$year}-02-12"],
            ['name' => 'วันจักรี', 'date' => "{$year}-04-06"],
            ['name' => 'วันสงกรานต์', 'date' => "{$year}-04-13"],
            ['name' => 'วันสงกรานต์', 'date' => "{$year}-04-14"],
            ['name' => 'วันสงกรานต์', 'date' => "{$year}-04-15"],
            ['name' => 'วันแรงงานแห่งชาติ', 'date' => "{$year}-05-01"],
            ['name' => 'วันพืชมงคล', 'date' => "{$year}-05-11"],
            ['name' => 'วันวิสาขบูชา', 'date' => "{$year}-05-22"],
            ['name' => 'วันเฉลิมพระชนมพรรษา', 'date' => "{$year}-06-03"],
            ['name' => 'วันเข้าพรรษา', 'date' => "{$year}-07-20"],
            ['name' => 'วันอาสาฬหบูชา', 'date' => "{$year}-07-20"],
            ['name' => 'วันเฉลิมพระชนมพรรษา', 'date' => "{$year}-07-28"],
            ['name' => 'วันเข้าคลอง', 'date' => "{$year}-08-12"],
            ['name' => 'วันวชิราวุธ', 'date' => "{$year}-09-23"],
            ['name' => 'วันปิยมหาราช', 'date' => "{$year}-10-23"],
            ['name' => 'วันลอยกระทง', 'date' => "{$year}-11-05"],
            ['name' => 'วันพ่อแห่งชาติ', 'date' => "{$year}-12-05"],
            ['name' => 'วันรัฐธรรมนูญ', 'date' => "{$year}-12-10"],
            ['name' => 'วันพ่อแห่งชาติ', 'date' => "{$year}-12-10"],
            ['name' => 'วันปีใหม่', 'date' => "{$year}-12-31"],
        ];
    }
}
