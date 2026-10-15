<?php

namespace App\Services;

use App\Models\ContributionEnvelope;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class JubileeEnvelopeService
{
    public static function generateQRCode(ContributionEnvelope $envelope)
    {
        $uuid = Str::uuid();
        $image = QrCode::format('png')
            ->size(300)
            ->backgroundColor(255, 255, 255)
            ->margin(1)
            ->generate(config('app.frontend_url') . "/40eme-anniversaire/enveloppe-verification/" . $uuid);
        $qrCodePath = 'qr_codes/qr_code_' . $uuid . '.png';
        Storage::disk('public')->put($qrCodePath, $image);

        try {
            DB::beginTransaction();
            if ($envelope->qr_code_id) {
                Storage::disk('public')->delete($envelope->qrcode_image_path);
            }
            $envelope->qr_code_id = $uuid;
            $envelope->qrcode_image_path = $qrCodePath;
            $envelope->save();
            DB::commit();
        } catch (\Exception $th) {
            DB::rollBack();
            Log::error($th);
        }
        return ["qrcode_image_path" => config("app.url") . "/storage/" . $qrCodePath];
    }

    public function generateAllEnvelopes(Collection $envelopes, $template)
    {
        $existingPdf = public_path('documents/' . $template);
        $file_names = [];

        // Chunk the envelopes into groups of 3
        $envelopes->chunk(3)->each(function (Collection $envelopeGroup) use (&$file_names, $existingPdf) {
            // Create a new PDF for this group of envelopes
            $pdf = new Fpdi();

            // Ensure the template exists
            if (!file_exists($existingPdf)) {
                Log::error("The PDF template file does not exist.", [$existingPdf]);
                return;
            }

            // Get page count from template
            $pageCount = $pdf->setSourceFile($existingPdf);
            Log::info("pageCount", [$pageCount]);

            // For each envelope in this group, add template pages and its data
            $envelopeData = [];
            foreach ($envelopeGroup as $envelope) {
                if (!$envelope->qrcode_image_path) {
                    // Generate QR code for this envelope
                    $this->generateQRCode($envelope);
                }
                $envelopeData[] = $this->getPdfData($envelope);
            }

            // Add each page of the template for this envelope
            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                // Import the page
                $templateId = $pdf->importPage($pageNo);

                // Add a page
                $pdf->AddPage();

                // Use the imported page
                $pdf->useTemplate($templateId);

                // Add the image only to the first page
                // if ($pageNo == 1) {
                //     foreach ($envelopeData as $idx =>  $envelopeDataItem) {
                //         foreach ($envelopeDataItem as $item) {
                //             if ($item["type"] == "image") {
                //                 $pdf->Image($item["path"], $item["x"], ($idx * 100) + $item["y"], 20);
                //             }
                //         }
                //     }
                // }

                if ($pageNo == 2) {
                    foreach ($envelopeData as $idx =>   $envelopeDataItem) {
                        foreach ($envelopeDataItem as $item) {
                            if ($item["type"] == "image") {
                                $pdf->Image($item["path"], $item["x"], ($idx * 100) + ($item["y"] - 45), 20);
                            } else {
                                // Add text to the PDF
                                $pdf->SetFont('Arial', '', 12);
                                $pdf->SetTextColor(0, 0, 250);
                                $pdf->SetXY($item["x"], ($idx * 100) + $item["y"]);
                                $pdf->Write(
                                    0,
                                    utf8_decode($item["text"])
                                );
                            }
                        }
                    }
                }
            }

            // Save the PDF for this group
            $ids = $envelopeGroup->pluck('id')->toArray();
            $fileName = 'envelope_' . implode('_', $ids) . '.pdf';
            $file_names[] = $fileName;
            $envelopePath = 'envelope/' . $fileName;

            // Delete old envelope files if they exist
            $envelopeGroup->each(function (ContributionEnvelope $envelope) {
                if ($envelope->envelope_path) {
                    Storage::disk('public')->delete($envelope->envelope_path);
                }
            });

            Storage::disk('public')->put($envelopePath, $pdf->Output('S'));

            // Update all envelopes in this group with the new path
            $envelopeGroup->each(function (ContributionEnvelope $envelope) use ($envelopePath) {
                try {
                    DB::beginTransaction();
                    $envelope->envelope_path = $envelopePath;
                    $envelope->template = "template_for_one_to_one";
                    $envelope->save();
                    DB::commit();
                } catch (\Exception $th) {
                    DB::rollBack();
                    Log::error($th);
                }
            });
        });

        // Create zip archive if there are multiple files
        if (count($file_names) > 1) {
            $zip = new \ZipArchive();
            $archivePath = 'envelope/archive_' . time() . '.zip';
            $zip->open(Storage::disk('public')->path($archivePath), \ZipArchive::CREATE);

            foreach ($file_names as $file_name) {
                $zip->addFile(Storage::disk('public')->path('envelope/' . $file_name), $file_name);
            }

            $zip->close();
            return ["archive_path" => $archivePath];
        } else {
            return ["file_path" => 'envelope/' . $file_names[0]];
        }
    }


    public function getPdfData(ContributionEnvelope $envelope)
    {
        $civility = ["Monsieur" => "M.", "Madame" => "Mme", "Mademoiselle" => "Mlle"];
        $phones_text = $envelope->recipient?->detail?->phone_numbers ?
            implode(' / ', gettype($envelope->recipient?->detail?->phone_numbers) === "string" ? json_decode($envelope->recipient?->detail?->phone_numbers) :
                $envelope->recipient?->detail?->phone_numbers) : $envelope->recipient?->detail?->phone;
        return [
            [
                "type" => "image",
                "path" => storage_path("app/public/" . $envelope->qrcode_image_path),
                "y" => 55,
                "x" => 160,
            ],
            [
                "type" => "name",
                "text" => ($civility[$envelope->recipient?->detail?->civility] ?? "") . " " . $envelope->recipient?->detail?->first_name . " " . $envelope->recipient?->detail?->last_name,
                "y" => 36,
                "x" => 45,
            ],
            [
                "type" => "promotion",
                "text" => $envelope->recipient?->hasRole('ancien') ? $envelope->recipient?->detail?->promotion_year : "Donateur 40eme",
                "y" => 41,
                "x" => 45,
            ],
            [
                "type" => "email",
                "text" => $envelope->recipient?->email,
                "y" => 45,
                "x" => 45,
            ],
            [
                "type" => "telephone",
                "text" => $phones_text,
                "y" => 50,
                "x" => 45,
            ],
        ];
    }
}
