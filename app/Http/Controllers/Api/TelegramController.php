<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\TelegramGroup;
use App\Services\TelegramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TelegramController extends Controller
{
    public function test(Request $request): JsonResponse
    {
        $employee = Employee::find($request->get("employee_id"));
        if (!$employee || !$employee->telegram_chat_id) {
            return response()->json(["success" => false, "message" => "No Telegram Chat ID"], 400);
        }
        $telegram = new TelegramService();
        $message = "Test notification

";
        $message .= "Name: {$employee->name}
";
        $message .= "Status: System OK";
        $result = $telegram->sendToChat($employee->telegram_chat_id, $message);
        if ($result) return response()->json(["success" => true, "message" => "Sent!"]);
        return response()->json(["success" => false, "message" => "Failed"], 500);
    }

    public function testGroup(Request $request): JsonResponse
    {
        $request->validate(["group_id" => "required|exists:telegram_groups,id"]);
        $group = TelegramGroup::find($request->group_id);
        $telegram = new TelegramService();
        $companyName = $group->company->name ?? "-";
        $message = "Test group notification

";
        $message .= "Group: {$group->group_name}
";
        $message .= "Company: {$companyName}
";
        $message .= "Status: System OK";
        $result = $telegram->sendToGroup($group, $message);
        if ($result) return response()->json(["success" => true, "message" => "Sent!"]);
        return response()->json(["success" => false, "message" => "Failed"], 500);
    }

    public function groups(Request $request): JsonResponse
    {
        $query = TelegramGroup::with("company");
        if ($request->company_id) $query->where("company_id", $request->company_id);
        return response()->json(["success" => true, "data" => $query->get()]);
    }

    public function storeGroup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            "company_id" => "required|exists:companies,id",
            "group_name" => "required|string|max:255",
            "group_type" => "required|in:company,branch,department,supervisor",
            "telegram_chat_id" => "required|string",
            "office_location_id" => "nullable|exists:office_locations,id",
        ]);
        $group = TelegramGroup::create($validated);
        return response()->json(["success" => true, "data" => $group, "message" => "Created"], 201);
    }

    public function updateGroup(Request $request, $id): JsonResponse
    {
        $group = TelegramGroup::findOrFail($id);
        $validated = $request->validate([
            "company_id" => "sometimes|exists:companies,id",
            "group_name" => "sometimes|string|max:255",
            "group_type" => "sometimes|in:company,branch,department,supervisor",
            "telegram_chat_id" => "sometimes|string",
            "office_location_id" => "nullable|exists:office_locations,id",
            "is_active" => "sometimes|boolean",
        ]);
        $group->update($validated);
        return response()->json(["success" => true, "data" => $group, "message" => "Updated"]);
    }

    public function deleteGroup($id): JsonResponse
    {
        TelegramGroup::findOrFail($id)->delete();
        return response()->json(["success" => true, "message" => "Deleted"]);
    }
}
