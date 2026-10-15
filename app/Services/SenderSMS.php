<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\TemplateMessage;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SenderSMS
{
    public static function sendSMS(string $to, string $message, ?User $userTo, ?TemplateMessage $template = null): bool
    {
        $apiKey = config('app.sms.befiana.api_key');
        $apiUrl = config('app.sms.befiana.api_url');

        if ($apiKey && $apiUrl) {
            try {
                $response = Http::acceptJson()
                    ->withOptions(['verify' => false])
                    ->withHeaders(['Authorization' => $apiKey])
                    ->post($apiUrl, [
                        'phone_number' => $to,
                        'message' => $message,
                    ]);

                $statusCode = $response->status();
                $isSuccess = in_array($statusCode, [200, 201]);
                DB::beginTransaction();

                $data = [
                    'to' => $to,
                    'message' => $message,
                    'status' => $isSuccess ? 'sent' : 'failed',
                    'log_data' => json_encode($response->json()),
                ];

                if ($template) {
                    $data['template'] = $template->id;
                }
                if ($userTo) {
                    $data['user_to_id'] = $userTo->id;
                }
                if (!!Auth::user()) {
                    $data['user_from_id'] = Auth::user()?->id;
                }

                Conversation::create($data);

                DB::commit();

                if (!$isSuccess) {
                    Log::error("Failed to send SMS: " . $response->body());
                }
                return $isSuccess;
            } catch (Exception $e) {
                DB::rollBack();
                Log::error("Error sending SMS: " . $e->getMessage());
                return false;
            }
        }

        Log::error("SMS API key or URL is not configured properly.");
        return false;
    }

    public static function getBalanceSMS()
    {
        $apiKey = config('app.sms.befiana.api_key');

        if ($apiKey) {
            try {
                $response = Http::acceptJson()
                    ->withOptions(['verify' => false])
                    ->withHeaders(['Authorization' => $apiKey])
                    ->get("https://api.befiana.cloud/api/smsko/v1/balance/");

                $statusCode = $response->status();
                if ($statusCode != 200) {
                    Log::error("Failed to get SMS balance: " . $response->body());
                    return false;
                }
                return $response->json();
            } catch (Exception $e) {
                Log::error("Error sending SMS: " . $e->getMessage());
                return false;
            }
        }

        Log::error("SMS API key or URL is not configured properly.");
        return false;
    }
}
