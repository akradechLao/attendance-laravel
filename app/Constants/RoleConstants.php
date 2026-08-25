<?php

namespace App\Constants;

class RoleConstants
{
    const EMPLOYEE = 'employee';
    const ADMIN = 'admin';
    const SUPER_ADMIN = 'super_admin';

    const ALL = [self::EMPLOYEE, self::ADMIN, self::SUPER_ADMIN];

    const LABELS = [
        self::EMPLOYEE => 'พนักงาน',
        self::ADMIN => 'HR Admin',
        self::SUPER_ADMIN => 'ผู้ดูแลระบบ',
    ];
}
