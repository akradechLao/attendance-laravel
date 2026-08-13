<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

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
        if (empty($this->botToken)) {
            return false;
        }

        try {
            $url = "https://api.telegram.org/bot{$this->botToken}/sendMessage";
            $response = Http::timeout(5)->post($url, [
                'chat_id' => $this->chatId,
                'text' => $message,
                'parse_mode' => 'HTML',
            ]);
            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    public function sendToChat(string $chatId, string $message): bool
    {
        if (empty($this->botToken) || empty($chatId)) {
            return false;
        }

        try {
            $url = "https://api.telegram.org/bot{$this->botToken}/sendMessage";
            $response = Http::timeout(5)->post($url, [
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => 'HTML',
            ]);
            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    public function sendPhotoToChat(string $chatId, string $base64Photo, string $caption): bool
    {
        if (empty($this->botToken) || empty($chatId)) {
            return false;
        }

        try {
            $url = "https://api.telegram.org/bot{$this->botToken}/sendPhoto";
            $imageData = base64_decode(preg_replace('/^data:image\/\w+;base64,/', '', $base64Photo));

            $response = Http::timeout(10)->attach(
                'photo', $imageData, 'photo.jpg'
            )->post($url, [
                'chat_id' => $chatId,
                'caption' => $caption,
                'parse_mode' => 'HTML',
            ]);

            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }
}
