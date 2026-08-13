<?php

namespace App\Services;

use App\Models\TelegramGroup;
use Illuminate\Support\Facades\Http;

class TelegramService
{
    protected string $botToken;

    public function __construct()
    {
        $this->botToken = config("services.telegram.bot_token", "");
    }

    public function sendToChat(string $chatId, string $message): bool
    {
        if (empty($this->botToken) || empty($chatId)) return false;
        try {
            $url = "https://api.telegram.org/bot{$this->botToken}/sendMessage";
            $response = Http::timeout(5)->post($url, [
                "chat_id" => $chatId,
                "text" => $message,
                "parse_mode" => "HTML",
            ]);
            return $response->successful();
        } catch (\Exception $e) { return false; }
    }

    public function sendToGroup(TelegramGroup $group, string $message): bool
    {
        return $this->sendToChat($group->telegram_chat_id, $message);
    }

    public function sendToCompanyGroups(int $companyId, string $message, ?string $groupType = null): int
    {
        $query = TelegramGroup::where("company_id", $companyId)->where("is_active", true);
        if ($groupType) $query->where("group_type", $groupType);
        $groups = $query->get();
        $sent = 0;
        foreach ($groups as $group) {
            if ($this->sendToGroup($group, $message)) $sent++;
        }
        return $sent;
    }

    public function sendToBranchGroups(int $officeLocationId, string $message): int
    {
        $groups = TelegramGroup::where("office_location_id", $officeLocationId)
            ->where("is_active", true)->get();
        $sent = 0;
        foreach ($groups as $group) {
            if ($this->sendToGroup($group, $message)) $sent++;
        }
        return $sent;
    }

    public function broadcast(string $message): int
    {
        $groups = TelegramGroup::where("is_active", true)->get();
        $sent = 0;
        foreach ($groups as $group) {
            if ($this->sendToGroup($group, $message)) $sent++;
        }
        return $sent;
    }

    public function sendPhotoToChat(string $chatId, string $base64Photo, string $caption): bool
    {
        if (empty($this->botToken) || empty($chatId)) return false;
        try {
            $url = "https://api.telegram.org/bot{$this->botToken}/sendPhoto";
            $imageData = base64_decode(preg_replace("/\^data:image\/\w+;base64,/", "", $base64Photo));
            $response = Http::timeout(10)->attach("photo", $imageData, "photo.jpg")
                ->post($url, [
                    "chat_id" => $chatId,
                    "caption" => $caption,
                    "parse_mode" => "HTML",
                ]);
            return $response->successful();
        } catch (\Exception $e) { return false; }
    }

    public static function getBotInfo(): ?array
    {
        $botToken = config("services.telegram.bot_token", "");
        if (empty($botToken)) return null;
        try {
            $response = Http::timeout(5)->get("https://api.telegram.org/bot{$botToken}/getMe");
            if ($response->successful()) return $response->json("result");
        } catch (\Exception $e) {}
        return null;
    }
}
