<?php

namespace App\Console\Commands;

use App\Models\AttendanceLog;
use Illuminate\Console\Command;

/**
 * แก้กรณีพนักงานกดปุ่มผิด - ตั้งใจจะสแกน "ออก" แต่ระบบไปสร้างรอบใหม่เป็นสแกน "เข้า" แทน
 * (เช่น กดปุ่มเช็คอินซ้ำทั้งที่ยังไม่ได้เช็คเอาท์รอบก่อนหน้า) - ย้ายเวลา/ข้อมูลจากรอบที่สร้าง
 * ผิดไปเป็นเวลาเช็คเอาท์ของรอบก่อนหน้าที่ยังเปิดอยู่ แล้วลบรอบที่สร้างผิดทิ้ง
 *
 * ค่าเริ่มต้นเป็น dry-run ต้องใส่ --apply ถึงจะเขียนจริง
 */
class MergeMistakenCheckin extends Command
{
    protected $signature = 'attendance:merge-mistaken-checkin
        {log_id : id ของแถว attendance_logs ที่สร้างผิด (ควรเป็นสแกนออก แต่ดันเป็นสแกนเข้ารอบใหม่)}
        {--target= : id ของรอบที่จะย้ายเวลาไปรวมด้วย (ไม่ใส่ = หารอบก่อนหน้าที่ check_out ยังว่างให้อัตโนมัติ)}
        {--apply : เขียนข้อมูลจริง (ไม่ใส่ = dry-run แสดงตัวอย่างอย่างเดียว)}';

    protected $description = 'ย้ายรอบสแกนเข้าที่สร้างผิด (ตั้งใจจะสแกนออก) ไปเป็นเวลาเช็คเอาท์ของรอบก่อนหน้า แล้วลบรอบที่ผิด';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $mistaken = AttendanceLog::with('employee')->find($this->argument('log_id'));

        if (!$mistaken) {
            $this->error('ไม่พบ attendance_logs id=' . $this->argument('log_id'));
            return Command::FAILURE;
        }

        $date = $mistaken->date instanceof \Carbon\Carbon ? $mistaken->date->toDateString() : (string) $mistaken->date;

        if ($targetId = $this->option('target')) {
            $target = AttendanceLog::find($targetId);
            if (!$target) {
                $this->error('ไม่พบแถวเป้าหมาย id=' . $targetId);
                return Command::FAILURE;
            }
            if ($target->check_out) {
                $existingCheckout = $target->check_out instanceof \Carbon\Carbon ? $target->check_out->format('H:i:s') : $target->check_out;
                $estimateNote = $target->is_estimated ? ' (is_estimated=true - ระบบเติมให้เอง ไม่ใช่ของจริง)' : '';
                $this->warn("*** เตือน: รอบเป้าหมาย #{$target->id} มีเวลาเช็คเอาท์อยู่แล้ว ({$existingCheckout}){$estimateNote} จะถูกเขียนทับ ***");
            }
        } else {
            $target = AttendanceLog::where('emp_id', $mistaken->emp_id)
                ->whereDate('date', $date)
                ->where('id', '!=', $mistaken->id)
                ->where('round_no', '<', $mistaken->round_no)
                ->whereNull('check_out')
                ->orderBy('round_no', 'desc')
                ->first();

            if (!$target) {
                $this->error('ไม่พบรอบก่อนหน้าที่ยังไม่ได้เช็คเอาท์ในวันเดียวกัน (emp_id=' . $mistaken->emp_id . ', date=' . $date . ') - อาจมีรอบก่อนหน้าที่มีเวลาเช็คเอาท์ผิดอยู่แล้ว (เช่น auto-estimate) ลองระบุรอบเป้าหมายตรงๆ ด้วย --target=<id>');
                return Command::FAILURE;
            }
        }

        $checkInTime = $mistaken->check_in instanceof \Carbon\Carbon ? $mistaken->check_in->format('H:i:s') : (string) $mistaken->check_in;

        $this->info("พนักงาน: {$mistaken->employee->name} วันที่ {$date}");
        $this->info("รอบที่สร้างผิด: #{$mistaken->id} (round_no={$mistaken->round_no}) เช็คอิน {$checkInTime}");
        $this->info("จะย้ายไปเป็นเวลาเช็คเอาท์ของ: #{$target->id} (round_no={$target->round_no}, เช็คอินเดิม " .
            ($target->check_in instanceof \Carbon\Carbon ? $target->check_in->format('H:i:s') : $target->check_in) . ')');
        $this->line("  check_out ใหม่: {$checkInTime}");
        if ($mistaken->face_image) {
            $this->line('  จะย้าย face_image ของรอบที่ผิด ไปเป็น check_out_face_image ของรอบเป้าหมายด้วย');
        }
        $this->warn("หลังทำเสร็จ: แถว #{$mistaken->id} จะถูกลบทิ้ง");
        $this->newLine();

        if (!$apply) {
            $this->warn('*** DRY RUN *** ไม่มีการเขียนข้อมูลจริง - ใส่ --apply เพื่อดำเนินการจริง');
            return Command::SUCCESS;
        }

        \DB::transaction(function () use ($target, $mistaken, $checkInTime) {
            $update = ['check_out' => $checkInTime];
            if ($target->is_estimated) {
                $update['is_estimated'] = false;
            }
            if ($mistaken->face_image) {
                $update['check_out_face_image'] = $mistaken->face_image;
            }
            if ($mistaken->remote_latitude && $mistaken->remote_longitude) {
                $update['remote_latitude'] = $mistaken->remote_latitude;
                $update['remote_longitude'] = $mistaken->remote_longitude;
                $update['remote_accuracy'] = $mistaken->remote_accuracy;
            }
            $target->update($update);
            $mistaken->delete();
        });

        $this->info("เรียบร้อย: รอบ #{$target->id} ได้เวลาเช็คเอาท์ {$checkInTime} แล้ว และลบแถว #{$mistaken->id} ที่สร้างผิดทิ้งแล้ว");

        return Command::SUCCESS;
    }
}
