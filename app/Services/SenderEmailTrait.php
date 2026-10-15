<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

trait SenderEmailTrait
{
    /**
     * @param  User[]|Collection|string[]  $listTos
     * @param  User[]|Collection|string[]|[]  $listBcc
     */
    private function sendEmail(string $EmailClass, array $emailArgs, $listTos, $listBcc = []): void
    {
        $dataSender = [
            'name'  => 'ACU Vontovorona',
            'email' => 'no-reply@acut-vontovorona.org',
        ];

        $dataTo  = [];
        $dataBcc = [];

        if (config('app.env') === 'local') {
            $dataTo[] = [
                'name'  => env('MAIL_TEST', "rafanorenantsoasarobidy@gmail.com"),
                'email' => env('MAIL_TEST', "rafanorenantsoasarobidy@gmail.com"),
            ];
            $dataBcc[] = [
                'name'  => env('MAIL_TEST', "rafanorenantsoasarobidy@gmail.com"),
                'email' => env('MAIL_TEST', "rafanorenantsoasarobidy@gmail.com"),
            ];
            Log::info("Local env", $dataTo);
        } else {
            $dataBcc = [
                [
                    'email' => env('MAIL_BCC_DEFAULT', "rafanorenantsoasarobidy@gmail.com"),
                ],
            ];
            foreach ($listTos as $to) {
                if (gettype($to) === 'string') {
                    $dataTo[] = [
                        'name'  => $to,
                        'email' => $to,
                    ];
                } else {
                    $dataTo[] = [
                        'name'  => $to->detail ? $to->detail->last_name . ' ' . $to->detail->first_name  : $to->email,
                        'email' => $to->email,
                    ];
                }
            }
            if (count($listBcc) > 0) {
                foreach ($listBcc as $bcc) {
                    if (gettype($to) === 'string') {
                        $dataTo[] = [
                            'email' => $bcc,
                        ];
                    } else {
                        $dataTo[] = [
                            'email' => $bcc->email,
                        ];
                    }
                }
            }
            Log::info("prod env", $dataTo);
        }

        $ref   = new \ReflectionClass($EmailClass);
        $email = $ref->newInstanceArgs([...$emailArgs]);
        //$email = new $EmailClass(...$emailArgs);
        $subject      = $email->subject ? $email->subject : $email->title;
        $content      = $email->render();
        $isSent       = SenderEmail::sendEmail($subject, $dataSender, $dataTo, $dataBcc, $content);
        $startLogText = "{$EmailClass} envoyé à";

        if ($isSent) {
            Log::info("{$startLogText}", $dataTo);
        } else {
            Log::error("Echec : {$startLogText}", $dataTo);
        }
    }
}
