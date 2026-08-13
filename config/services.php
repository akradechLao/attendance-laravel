<?php

return [
    "face_api" => [
        "url" => env("FACE_API_URL", "http://127.0.0.1:8000"),
    ],
    "telegram" => [
        "bot_token" => env("TELEGRAM_BOT_TOKEN", ""),
        "chat_id" => env("TELEGRAM_CHAT_ID", ""),
    ],
];
