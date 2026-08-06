<?php

namespace App\Services;

use App\Models\AttendanceLog;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    protected string $botToken;
    protected string $chatId;

    public function __construct()
    {
        $this->botToken = config('services.telegram.bot_token', '');
        $this->chatId = config('services.telegram.chat_id', '');
    }

    public function sendMessage(string $message): bool
    {
        if (empty($this->botToken) || empty($this->chatId)) {
            Log::warning('Telegram bot token or chat ID not configured');
            return false;
        }

        try {
            $response = Http::timeout(10)->post("https://api.telegram.org/bot{$this->botToken}/sendMessage", [
                'chat_id' => $this->chatId,
                'text' => $message,
                'parse_mode' => 'HTML',
            ]);

            if ($response->successful()) {
                return true;
            }

            Log::error('Telegram API error: ' . $response->body());
            return false;
        } catch (\Exception $e) {
            Log::error('Telegram send message error: ' . $e->getMessage());
            return false;
        }
    }

    public function sendCheckInNotification(Employee $employee, AttendanceLog $log): bool
    {
        $statusEmoji = $log->status === 'late' ? ' Late' : ' On Time';
        $message = sprintf(
            "<b>Check-In Notification</b>\n\n" .
            "Employee: <b>%s</b>\n" .
            "Employee ID: %s\n" .
            "Date: %s\n" .
            "Time: %s\n" .
            "Status: %s%s",
            $employee->first_name . ' ' . $employee->last_name,
            $employee->employee_id,
            $log->date->format('d/m/Y'),
            $log->check_in,
            $log->status === 'late' ? 'LATE' : 'ON TIME',
            $statusEmoji
        );

        return $this->sendMessage($message);
    }

    public function sendCheckOutNotification(Employee $employee, AttendanceLog $log): bool
    {
        $checkInTime = Carbon::parse($log->check_in);
        $checkOutTime = Carbon::parse($log->check_out);
        $workingHours = $checkInTime->diffInHours($checkOutTime);

        $message = sprintf(
            "<b>Check-Out Notification</b>\n\n" .
            "Employee: <b>%s</b>\n" .
            "Employee ID: %s\n" .
            "Date: %s\n" .
            "Check-Out Time: %s\n" .
            "Working Hours: %d hours",
            $employee->first_name . ' ' . $employee->last_name,
            $employee->employee_id,
            $log->date->format('d/m/Y'),
            $log->check_out,
            $workingHours
        );

        return $this->sendMessage($message);
    }
}
