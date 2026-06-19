<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SendNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'type' => 'required|in:system_alert,welcome_message,payment_confirmation,status_update,support_ticket,event_invitation,event_reminder,reservation_confirmed,reservation_cancelled,account_verified,password_changed,profile_updated,promotion,maintenance,security_alert',
            'priority' => 'required|in:low,medium,high,critical',
            'recipients' => 'required|in:all,users,groups,centers,suppliers,guides,admins,custom',
            'user_ids' => 'required_if:recipients,custom|array',
            'user_ids.*' => 'exists:users,id',
            'channels' => 'nullable|array',
            'channels.*' => 'in:in_app,email,push,sms',
            'scheduled_at' => 'nullable|date|after:now',
            'expires_at' => 'nullable|date|after:scheduled_at',
            'action_url' => 'nullable|url',
            'action_text' => 'nullable|string|max:50',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120',
        ];
    }
}
