<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SenderEmail
{
    public static function sendEmail(string $subject, array $senderData, array $toData, ?array $dataBcc, string $html): bool
    {
        $apiKey = config('app.email.brevo.api_key');
        $apiUrl = config('app.email.brevo.api_url');

        Log::info($apiUrl . $apiKey);
        if ($apiKey != null && $apiUrl != null) {
            try {
                $postData = [
                    'sender' => [
                        'name' => $senderData['name'],
                        'email' => $senderData['email'],
                    ],
                    'to' => $toData,
                    'subject' => $subject,
                    'htmlContent' => $html,
                ];

                if ($dataBcc) {
                    $postData['bcc'] = $dataBcc;
                }
                $response = Http::acceptJson()
                    ->withOptions(['verify' => false])
                    ->withHeaders(['api-key' => $apiKey])
                    ->post($apiUrl, $postData);

                $response_status_code = $response->status();
                Log::debug("Envoi email par l'api brevo avec code reçu : {$response_status_code}");

                $isSuccess = in_array($response_status_code, [201, 200]);

                if (! $isSuccess) {
                    $body = $response->body();
                    Log::error("Erreur lors de l'envoi de l'email : ".$body);
                }

                return $isSuccess;
            } catch (Exception $e) {
                Log::error("Erreur lors de l'envoi de l'email : ". $e->getMessage());

                return false;
            }
        } else {
            Log::error("app.email.api_key ou app.email.api_url n'est pas renseigné ou est incorrect");

            return false;
        }
    }
}
