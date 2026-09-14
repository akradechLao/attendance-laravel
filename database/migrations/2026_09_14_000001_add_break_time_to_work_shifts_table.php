<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * เวลาพักเดิมเป็นค่าคงที่ฝังในโค้ด (60 นาที ช่วง 11:45-12:45) ใช้กับทุกกะเหมือนกันหมด
     * ทำให้กะกลางคืนไม่เคยถูกหักเวลาพักเลย (เวลาเข้า-ออกไม่เคยตรงช่วงกลางวัน) ย้ายมาตั้งค่า
     * ต่อกะแทน เพื่อให้ HR กำหนดเวลาพักที่เหมาะกับกะกลางวัน/กลางคืนแต่ละกะเองได้
     */
    public function up(): void
    {
        Schema::table('work_shifts', function (Blueprint $table) {
            $table->time('break_start_time')->nullable()->after('end_time');
            $table->time('break_end_time')->nullable()->after('break_start_time');
        });

        // ตั้งค่าเริ่มต้นให้ทั้ง 16 กะที่มีอยู่แล้ว - กะกลางวันใช้ช่วงพักเที่ยงเดิม (คงพฤติกรรม
        // เดิมไว้ไม่ให้ชั่วโมงทำงานเปลี่ยนกะทันที) ส่วนกะกลางคืน/กะข้ามคืน กำหนดพักกลางกะให้
        // (แก้ไขจุดที่กะกลางคืนไม่เคยถูกหักพักเลย) - HR ปรับได้อีกทีผ่านหน้าตั้งค่ากะ
        $defaults = [
            // group_number => [break_start, break_end]
            0  => ['12:00:00', '13:00:00'], // WC0001 07:30-16:30
            1  => ['12:00:00', '13:00:00'], // WC0002 08:00-17:00
            2  => ['20:00:00', '21:00:00'], // WC0003 16:00-01:00
            3  => ['04:00:00', '05:00:00'], // WC0004 00:00-09:00
            4  => ['13:00:00', '14:00:00'], // WC0005 09:00-18:00
            5  => ['00:00:00', '01:00:00'], // WC0006 20:00-05:00
            6  => ['01:00:00', '02:00:00'], // WC0007 21:00-06:00
            7  => ['12:00:00', '13:00:00'], // WC0008 08:00-16:30
            8  => ['20:00:00', '21:00:00'], // WC0009 16:00-00:30
            9  => ['04:00:00', '05:00:00'], // WC0010 00:00-08:30
            10 => ['12:00:00', '13:00:00'], // WC0011 08:00-20:00
            11 => ['00:00:00', '01:00:00'], // WC0012 20:00-08:00
            12 => ['20:00:00', '21:00:00'], // WC0013 16:00-00:00
            13 => ['04:00:00', '05:00:00'], // WC0014 00:00-08:00
            14 => ['11:30:00', '12:30:00'], // WC0015 07:00-16:00
            15 => ['23:00:00', '00:00:00'], // WC0016 19:00-04:00
        ];

        foreach ($defaults as $groupNumber => [$breakStart, $breakEnd]) {
            DB::table('work_shifts')
                ->where('group_number', $groupNumber)
                ->update(['break_start_time' => $breakStart, 'break_end_time' => $breakEnd]);
        }
    }

    public function down(): void
    {
        Schema::table('work_shifts', function (Blueprint $table) {
            $table->dropColumn(['break_start_time', 'break_end_time']);
        });
    }
};
