<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditLogService
{
    public static function log(
        string $action,
        ?Model $model,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $description = null,
        ?Request $request = null
    ): AuditLog {
        $userType = null;
        $userId = null;
        $userName = null;
        $ipAddress = null;

        if ($request) {
            $ipAddress = $request->ip();
            $user = $request->user();

            if ($user) {
                if ($user instanceof Employee) {
                    $userType = 'employee';
                    $userId = $user->id;
                    $userName = $user->name;
                } elseif (property_exists($user, 'name')) {
                    $userType = 'admin';
                    $userId = $user->id;
                    $userName = $user->name ?? $user->email;
                } elseif (property_exists($user, 'email')) {
                    $userType = 'admin';
                    $userId = $user->id;
                    $userName = $user->email;
                }
            }
        }

        if (!$description) {
            $description = $model ? self::generateDescription($action, $model) : $action;
        }

        return AuditLog::create([
            'user_type' => $userType,
            'user_id' => $userId,
            'user_name' => $userName,
            'action' => $action,
            'auditable_type' => $model ? get_class($model) : null,
            'auditable_id' => $model ? $model->getKey() : null,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'description' => $description,
            'ip_address' => $ipAddress,
        ]);
    }

    public static function created(Model $model, ?Request $request = null): AuditLog
    {
        return self::log('create', $model, null, $model->toArray(), null, $request);
    }

    public static function updated(Model $model, array $oldValues, ?Request $request = null): AuditLog
    {
        return self::log('update', $model, $oldValues, $model->toArray(), null, $request);
    }

    public static function deleted(Model $model, ?array $oldValues = null, ?Request $request = null): AuditLog
    {
        return self::log('delete', $model, $oldValues ?? $model->toArray(), null, null, $request);
    }

    public static function action(string $action, Model $model, ?string $description = null, ?Request $request = null): AuditLog
    {
        return self::log($action, $model, null, null, $description, $request);
    }

    private static function generateDescription(string $action, Model $model): string
    {
        $modelName = class_basename($model);
        $key = $model->getKey();

        $nameField = null;
        foreach (['name', 'title', 'subject'] as $field) {
            if ($model->getAttribute($field)) {
                $nameField = $field;
                break;
            }
        }
        $name = $nameField ? $model->getAttribute($nameField) : '#' . $key;

        $actionLabels = [
            'create' => 'สร้าง',
            'update' => 'แก้ไข',
            'delete' => 'ลบ',
            'approve' => 'อนุมัติ',
            'reject' => 'ไม่อนุมัติ',
        ];

        return ($actionLabels[$action] ?? $action) . ' ' . $modelName . ': ' . $name;
    }
}
