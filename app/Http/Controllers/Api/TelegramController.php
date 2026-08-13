<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Services\TelegramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TelegramController extends Controller
{
    public function test(Request $request): JsonResponse
    {
        $employee = Employee::find($request->get('employee_id'));

        if (!$employee || !$employee->telegram_chat_id) {
            return response()->json([
                'success' => false,
                'message' => 'ไม่พบ Telegram Chat ID',
            ], 400);
        }

        $telegram = new TelegramService();
        $message = "✅ <b>ทดสอบแจ้งเตือนระบบ attendance</b>\n\n";
        $message .= "👤 <b>ชื่อ:</b> {$employee->name}\n";
        $message .= "📍 <b>สถานะ:</b> ระบบทำงานปกติ";

        $result = $telegram->sendToChat($employee->telegram_chat_id, $message);

        if ($result) {
            return response()->json(['success' => true, 'message' => 'ส่งข้อความทดสอบสำเร็จ!']);
        }

        return response()->json(['success' => false, 'message' => 'ส่งไม่สำเร็จ กรุณาตรวจสอบ Chat ID'], 500);
    }
}
