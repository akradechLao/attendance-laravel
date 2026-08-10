<?php

namespace App\Constants;

class LeaveConstants
{
    const STATUS = [
        'pending' => 'pending',
        'approved' => 'approved',
        'rejected' => 'rejected',
        'cancelled' => 'cancelled',
    ];

    const TYPE = [
        'sick' => 'sick',
        'personal' => 'personal',
        'annual' => 'annual',
        'vacation' => 'vacation',
        'maternity' => 'maternity',
        'paternity' => 'paternity',
        'wfh' => 'wfh',
        'training' => 'training',
    ];

    const STATUS_LABELS = [
        'pending' => 'รออนุมัติ',
        'approved' => 'อนุมัติ',
        'rejected' => 'ไม่อนุมัติ',
        'cancelled' => 'ยกเลิก',
    ];

    const TYPE_LABELS = [
        'sick' => 'ลากิจ',
        'personal' => 'ลาป่วย',
        'annual' => 'ลาพักร้อน',
        'vacation' => 'ลาพักร้อน',
        'maternity' => 'ลาคลอด',
        'paternity' => 'ลาคลอด',
        'wfh' => 'ปฏิบัติงานนอกสถานที่',
        'training' => 'อบรม',
    ];
}
