<?php

namespace App\Http\Controllers;

use App\Events\SendEnvelopeTriggered;
use App\Models\User;
use setasign\Fpdi\Fpdi;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\ContributionEnvelope;
use Illuminate\Support\Facades\Mail;
use App\Mail\EnvelopeSentNotification;
use App\Jobs\EnvelopeBulkGenerationJob;
use Illuminate\Support\Facades\Storage;
use App\Services\JubileeEnvelopeService;
use Illuminate\Container\Attributes\Auth;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Carbon\Carbon;


class EnvelopeJubileeController extends Controller
{
    public function getAllComents()
    {
        $envelopesComments = ContributionEnvelope::orderBy("updated_at", "desc")->where("commentary", "!=", "")->get()->map(function ($item) {
            return [
                "id" => $item,
                'recipient_name' => $item->recipient?->detail?->first_name . " " . $item->recipient?->detail?->last_name,
                'responsible_name' => $item->responsible?->detail?->first_name,
                'recipient_phone' => $item->recipient?->detail?->phone_numbers ?
                    implode(' / ', gettype($item->recipient?->detail?->phone_numbers) === "string" ? json_decode($item->recipient?->detail?->phone_numbers) :
                        $item->recipient?->detail?->phone_numbers) : $item->recipient?->detail?->phone,
                'responsable_phone' => $item->responsible?->detail?->phone_numbers ?
                    implode(' / ', gettype($item->responsible?->detail?->phone_numbers) === "string" ? json_decode($item->responsible?->detail?->phone_numbers) :
                        $item->responsible?->detail?->phone_numbers) : $item->responsible?->detail?->phone,
                'comments' => $item->commentary,
                'status' => $item->status,
                'mean_of_delivery' => $item->mean_of_delivery,
                'updated_at' => $item->updated_at
            ];
        })->toArray();
        return response()->json($envelopesComments);
    }

    public function createEnvelope(Request $request, $forId)
    {
        if (!$forId) {
            return response()->json(['status' => 'recipient_id missing']);
        }
        $envelope = ContributionEnvelope::create([
            'recipient_id' => $forId,
            'responsible_id' => $request->responsible_id ?? $request->user()->id,
            'reason' => '40e'
        ]);
        JubileeEnvelopeService::generateQRCode($envelope);
        return response()->json([
            'id' => $envelope->id
        ], 201);
    }

    public function getEnvelope($id)
    {
        $envelope = ContributionEnvelope::where("id", $id)->first();
        $envelope->recipient_phone = $envelope->recipient?->detail?->phone_numbers ?
            implode(' / ', gettype($envelope->recipient?->detail?->phone_numbers) === "string" ? json_decode($envelope->recipient?->detail?->phone_numbers) :
                $envelope->recipient?->detail?->phone_numbers) : $envelope->recipient?->detail?->phone;
        $envelope->recipientHasRoleDonor = $envelope->recipient?->hasRole('donateur');
        return response()->json($envelope);
    }

    public function envelopeCreateQrCode(Request $request, $id)
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
            $envelope = ContributionEnvelope::where("id", $id)->firstOrFail();
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

        return response()->json(["qrcode_image_path" => config("app.url") . "/storage/" . $qrCodePath]);
    }

    public function deleteEnvelope(Request $request, $id)
    {
        $request->validate([
            'id' => ['required', 'exists:envelopes,id']
        ]);
        $envelope = ContributionEnvelope::where("id", $id)->first();
        $envelope->delete();
        return response()->noContent();
    }

    public function deleteEnvelopes(Request $request)
    {
        $request->validate([
            "ids" => ["required", "array", function ($attribute, $value, $fail) {
                if (!ContributionEnvelope::whereIn("id", $value)->exists()) {
                    $fail("The " . $attribute . " must contain existing users IDs");
                }
            }],
        ]);
        try {
            DB::beginTransaction();
            $envelopes = ContributionEnvelope::whereIn("id", $request->ids)->get();
            $envelopes->each(function ($envelope) {
                $envelope->delete();
            });
            DB::commit();
        } catch (\Exception $th) {
            DB::rollBack();
            Log::error("Error when deleting user :" . $th->getMessage());
            return response()->json($th->getMessage(), 500)->header('Content-Type', 'application/json')->throwResponse();
        }
        return response()->noContent();
    }

    public function updateEnvelope(Request $request, $id)
    {
        $request->validate([
            'status' => ['required', 'in:to_be_delivered,contacted,printed,delivered,money_ok,lost,not_reachable,invalid_phone'],
            'earnings' => ['numeric', "nullable"],
            'responsible_id' => ['exists:users,id', "nullable"],
            'assistant_responsible_id' => ['exists:users,id', "nullable"],
            'commentary' => ["string", "nullable"],
            'mean_of_delivery' => ["string", "nullable"],
            'payment_method' => ["string", "nullable"]
        ]);

        $envelope = ContributionEnvelope::where("id", $id)->first();
        try {
            DB::beginTransaction();
            if ($envelope->status != $request->status) {
                if ($request->status == 'contacted') {
                    $envelope->contacted_at = Carbon::now();
                }
                if ($request->status == 'printed') {
                    $envelope->printed_at = Carbon::now();
                }
                if ($request->status == 'delivered') {
                    $envelope->delivered_at = Carbon::now();
                }
                if ($request->status == 'money_ok') {
                    $envelope->money_ok_at = Carbon::now();
                }
                if ($request->status == 'lost') {
                    $envelope->lost_at = Carbon::now();
                }
                if ($request->status == 'not_reachable') {
                    $envelope->not_reachable_at = Carbon::now();
                }
                if ($request->status == 'invalid_phone') {
                    $envelope->invalid_phone_at = Carbon::now();
                }
            }

            if ($envelope->status != 'money_ok' && !!$request->earnings) {
                $envelope->status = 'money_ok';
                $envelope->money_ok_at = Carbon::now();
            } else {
                $envelope->status = $request->status;
            }
            $envelope->earnings = $request->earnings;
            $envelope->responsible_id = $request->responsible_id;
            $envelope->assistant_responsible_id = $request->assistant_responsible_id;
            $envelope->commentary = $request->commentary;
            $envelope->mean_of_delivery = $request->mean_of_delivery;
            $envelope->payment_method = $request->payment_method;
            $envelope->save();
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error when updating envelope :" . $e->getMessage());
        }
        return response()->json($this->getEnvelopeDetail($envelope));
    }

    public function getEnvelopeList(Request $request)
    {
        $pagination      = $request->get('perPage', 10);
        $status          = $request->get('status');
        $recipientName   = $request->get('recipientName');
        $responsible_id = $request->get('responsible_id');
        $meanOfDelivery = $request->get('mean_of_delivery');
        $onlyForMe = $request->get('onlyForMe');
        $data = ContributionEnvelope::when(!!$status, fn($query) => $query->where("status", "=", $status))
            ->when(!!$recipientName, fn($query) => $query->whereHas("recipient", function ($query) use ($recipientName) {
                $query->where("name", "like", "%$recipientName%");
            }))->when(!!$responsible_id, fn($query) => $query->where(function ($query) use ($responsible_id) {
                $query->whereHas("responsible", function ($query) use ($responsible_id) {
                    $query->where("id", $responsible_id);
                })->orWhereHas("assistantResponsible", function ($query) use ($responsible_id) {
                    $query->where("id", $responsible_id);
                });
            }))
            ->when($onlyForMe === "true", fn($query) => $query->where(function ($query) use ($request) {
                $query->where("responsible_id", $request->user()->id)
                    ->orWhere("assistant_responsible_id", $request->user()->id);
            }))
            ->when(!!$meanOfDelivery, fn($query) => $query->where("mean_of_delivery", $meanOfDelivery))
            ->paginate($pagination)
            ->withQueryString()
            ->through(fn($envelope) => $this->getEnvelopeDetail($envelope))->toArray();
        return response()->json($data);
    }

    public function getEnvelopeDetail(?ContributionEnvelope $envelope)
    {
        return $envelope ? [
            "id" => $envelope->id,
            "recipient" => [
                "id" => $envelope->recipient_id,
                "email" => $envelope->recipient?->email,
                "civility" => $envelope->recipient?->detail?->civility,
                "last_name" => $envelope->recipient?->detail?->last_name,
                "first_name" => $envelope->recipient?->detail?->first_name,
                "recipientHasRoleDonor" => $envelope->recipient?->hasRole('donateur'),
                "phone" => $envelope->recipient?->detail?->phone_numbers ?
                    implode(' / ', gettype($envelope->recipient?->detail?->phone_numbers) === "string" ? json_decode($envelope->recipient?->detail?->phone_numbers) :
                        $envelope->recipient?->detail?->phone_numbers) : $envelope->recipient?->detail?->phone,
            ],
            "responsible" => [
                "id" => $envelope->responsible_id,
                "email" => $envelope->responsible?->email,
                "civility" => $envelope->responsible?->detail?->civility,
                "last_name" => $envelope->responsible?->detail?->last_name,
                "first_name" => $envelope->responsible?->detail?->first_name,
                "phone" => $envelope->responsible?->detail?->phone,
            ],
            "assistant_responsible" => [
                "id" => $envelope->assistant_responsible_id,
                "email" => $envelope->assistantResponsible?->email,
                "civility" => $envelope->assistantResponsible?->detail?->civility,
                "last_name" => $envelope->assistantResponsible?->detail?->last_name,
                "first_name" => $envelope->assistantResponsible?->detail?->first_name,
                "phone" => $envelope->assistantResponsible?->detail?->phone,
            ],
            "status" => $envelope->status,
            "earnings" => $envelope->earnings,
            "mean_of_delivery" => $envelope->mean_of_delivery,
            "payment_method" => $envelope->payment_method,
            "commentary" => $envelope->commentary,
            "qr_code_id" => $envelope->qr_code_id,
            "template" => $envelope->template,
            "envelope_path" => !!$envelope->envelope_path ? config("app.url") . "/storage/" . $envelope->envelope_path : null,
            "qrcode_image_path" => config("app.url") . "/storage/" . $envelope->qrcode_image_path
        ] : [];
    }

    public function validateEnvelope(Request $request)
    {
        $request->validate([
            'envelopeId' => ['required', 'exists:contribution_envelopes,qr_code_id']
        ]);
        $envelope = ContributionEnvelope::where("qr_code_id", $request->envelopeId)->first();
        return response()->json([
            "qr_code_id" => $envelope->qr_code_id,
            "recipient" => [
                "civility" => $envelope->recipient?->detail?->civility,
                "last_name" => $envelope->recipient?->detail?->last_name,
                "first_name" => $envelope->recipient?->detail?->first_name,
            ],
            "responsible" => [
                "civility" => $envelope->responsible?->detail?->civility,
                "last_name" => $envelope->responsible?->detail?->last_name,
                "first_name" => $envelope->responsible?->detail?->first_name,
            ],
            "assistant_responsible" => [
                "civility" => $envelope->assistant_responsible?->detail?->civility,
                "last_name" => $envelope->assistant_responsible?->detail?->last_name,
                "first_name" => $envelope->assistant_responsible?->detail?->first_name,
            ],
            "status" => $envelope->status,
            "earnings" => $envelope->earnings,
            "mean_of_delivery" => $envelope->mean_of_delivery,
        ]);
    }

    public function updateEarning(Request $request)
    {
        $request->validate([
            'envelopeId' => ['required', 'exists:contribution_envelopes,qr_code_id'],
            'earnings' => ['required', 'numeric'],
        ]);

        try {
            $envelope = ContributionEnvelope::where("qr_code_id", $request->envelopeId)->first();
            if (!!$request->earnings) {
                $envelope->status = 'money_ok';
                $envelope->earnings = $request->earnings;
                $envelope->money_ok_at = Carbon::now();
                $envelope->payment_method = 'cash';
                $envelope->save();
            }
            return response()->json([
                "qr_code_id" => $envelope->qr_code_id,
                "message" => "Montant enregistré avec succès"
            ]);
        } catch (\Exception $e) {
            return response()->json([
                "qr_code_id" => $envelope->qr_code_id,
                "message" => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate a PDF with the given envelope's qrcode image.
     * @param int $id The id of the envelope to generate the PDF for.
     * @return \Illuminate\Http\Response A response with the generated PDF.
     */
    public function generateEnvelopePdf($id)
    {
        $envelope = ContributionEnvelope::where("id", $id)->first();

        $civility = ["Monsieur" => "M.", "Madame" => "Mme", "Mademoiselle" => "Mlle"];

        $phones_text = $envelope->recipient?->detail?->phone_numbers ?
            implode(' / ', gettype($envelope->recipient?->detail?->phone_numbers) === "string" ? json_decode($envelope->recipient?->detail?->phone_numbers) :
                $envelope->recipient?->detail?->phone_numbers) : $envelope->recipient?->detail?->phone;
        $data = [
            [
                "type" => "image",
                "path" => storage_path("app/public/" . $envelope->qrcode_image_path),
                "y" => 155
            ],
            [
                "type" => "image",
                "path" => storage_path("app/public/" . $envelope->qrcode_image_path),
                "y" => 210
            ],
            [
                "type" => "name",
                "text" => $envelope->recipient?->detail?->my_job && $envelope->recipient?->hasRole('donateur') ?
                    ($civility[$envelope->recipient?->detail?->civility] ?? "") . " " . $envelope->recipient?->detail?->last_name . " (" . $envelope->recipient?->detail?->my_job . ")"
                    : ($civility[$envelope->recipient?->detail?->civility] ?? "") . " " . $envelope->recipient?->detail?->first_name . " " . $envelope->recipient?->detail?->last_name,
                "y" => 236,
            ],
            [
                "type" => "promotion",
                "text" => $envelope->recipient?->hasRole('ancien') ? $envelope->recipient?->detail?->promotion_year : "Donateur 40eme",
                "y" => 241,
            ],
            [
                "type" => "email",
                "text" => $envelope->recipient?->email && !str_contains($envelope->recipient?->email, 'fictif') ? $envelope->recipient?->email : "",
                "y" => 245,
            ],
            [
                "type" => "telephone",
                "text" => $phones_text,
                "y" => 250,
            ],
        ];
        // Path to your existing PDF
        $existingPdf = public_path('documents/invitation_template_1.pdf');


        // Create new PDF instance
        $pdf = new Fpdi();

        // Get the number of pages from the source file
        $pageCount = $pdf->setSourceFile($existingPdf);

        // Loop through each page of the original PDF
        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            // Import the page
            $templateId = $pdf->importPage($pageNo);

            // Add a page
            $pdf->AddPage();

            // Use the imported page
            $pdf->useTemplate($templateId);

            // Add the image only to the first page (you can modify this condition as needed)
            if ($pageNo == 1) {
                foreach ($data as  $item) {
                    if ($item["type"] == "image") {
                        // Add image (x, y, width, height)
                        // Adjust these values to position your image correctly
                        $pdf->Image($item["path"], 160, $item["y"], 20);
                    } else {
                        // Add text to the PDF
                        $pdf->SetFont('Arial', '', 12);
                        $pdf->SetTextColor(0, 0, 250); // Red color
                        $pdf->SetXY(45, $item["y"]); // Position on the page
                        $pdf->Write(
                            0,
                            utf8_decode($item["text"])
                        );
                    }
                }
            }
        }

        if ($envelope->envelope_path) {
            Storage::disk('public')->delete($envelope->envelope_path);
        }
        $fileName = 'envelope_' . $envelope->qr_code_id . '.pdf';
        $envelopePath = 'envelope/' . $fileName;
        Storage::disk('public')->put($envelopePath, $pdf->Output('S'));

        try {
            DB::beginTransaction();
            $envelope->envelope_path = $envelopePath;
            $envelope->template = "template_for_email";
            $envelope->save();
            DB::commit();
        } catch (\Exception $th) {
            DB::rollBack();
            Log::error($th);
        }
        return response()->json(["envelope_path" => config("app.url") . "/storage/" . $envelopePath]);
    }

    public function generateEnvelopePdfBulk(Request $request)
    {
        $request->validate([
            "ids" => ["required", "array", function ($attribute, $value, $fail) {
                if (!ContributionEnvelope::whereIn("id", $value)->exists()) {
                    $fail("The " . $attribute . " must contain existing users IDs");
                }
            }],
        ]);


        try {
            EnvelopeBulkGenerationJob::dispatch($request->ids);
        } catch (\Exception $th) {
            DB::rollBack();
            Log::error("Error when deleting user :" . $th->getMessage());
            return response()->json($th->getMessage(), 500)->header('Content-Type', 'application/json')->throwResponse();
        }
        return response()->json(["message" => "job processing..."], 201);
    }

    public function sendEnvelopeByEmail(Request $request)
    {
        $request->validate([
            'id' => ['required', 'integer', 'exists:contribution_envelopes,id']
        ]);
        try {
            $envelope = ContributionEnvelope::findOrFail($request->id);
            if (strpos($envelope->recipient?->email, 'fictif') === false) {
                event(new SendEnvelopeTriggered($envelope));
                return response()->json(["message" => "email sent"], 201);
            }
            return response()->json(["message" => "email was not sent"], 404);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return response()->json($e->getMessage(), 500)->header('Content-Type', 'application/json')->throwResponse();
        }
    }

    /**
     * List envelopes grouped by week, filtered by status
     * Columns: destinataire, responsable, montant
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function listGroupedByWeek(Request $request)
    {
        $status = $request->get('status');
        $query = \App\Models\ContributionEnvelope::query();
        if ($status) {
            $query->where('status', $status);
        }
        if ($status != "to_be_delivered") {
            $statusDateColumnName = $status . "_at";
        } else {
            $statusDateColumnName = "created_at";
        }
        // Group by week (of created_at)
        $envelopes = $query
            ->select([
                'id',
                'recipient_id',
                'responsible_id',
                'earnings',
                'status',
                $statusDateColumnName,
                \DB::raw('CONCAT(YEAR(' . $statusDateColumnName . '), "-", LPAD(WEEK(' . $statusDateColumnName . ', 1), 2, "0")) as year_week'),
            ])
            ->with(['recipient.detail', 'responsible.detail'])
            ->orderBy($statusDateColumnName, 'asc')
            ->get();

        // Group by year_week
        $grouped = $envelopes->groupBy('year_week')->map(function ($items, $week) use ($statusDateColumnName) {
            return [
                'week' => $week,
                'start_date' => $week ? Carbon::now()->setISODate(...explode("-", $week))->startOfWeek()->format('Y-m-d') : null,
                'end_date' => $week ? Carbon::now()->setISODate(...explode("-", $week))->endOfWeek()->format('Y-m-d') : null,
                'envelopes' => $items->map(function ($item) use ($statusDateColumnName) {
                    return [
                        'id' => $item->id,
                        'destinataire' => $item->recipient?->detail?->first_name . ' ' . $item->recipient?->detail?->last_name,
                        'responsable' => $item->responsible?->detail?->first_name . ' ' . $item->responsible?->detail?->last_name,
                        'status' => $item->status,
                        'montant' => $item->earnings,
                        'date' => $item->{$statusDateColumnName} ? Carbon::make($item->{$statusDateColumnName})->setTimezone('Africa/Cairo')->format("Y-m-d H:i") : "",
                        'phone_recipient' => $item->recipient?->detail?->phone_numbers ?
                            implode(' / ', gettype($item->recipient?->detail?->phone_numbers) === "string" ? json_decode($item->recipient?->detail?->phone_numbers) :
                                $item->recipient?->detail?->phone_numbers) : $item->recipient?->detail?->phone,
                        'phone_responsible' => $item->responsible?->detail?->phone_numbers ?
                            implode(' / ', gettype($item->responsible?->detail?->phone_numbers) === "string" ? json_decode($item->responsible?->detail?->phone_numbers) :
                                $item->responsible?->detail?->phone_numbers) : $item->responsible?->detail?->phone,
                    ];
                })->values()->toArray(),
            ];
        })->values()->toArray();

        return response()->json($grouped);
    }

    public function listGroupedByResponsible(Request $request)
    {
        $responsible_id = $request->get('responsible_id');
        $query = \App\Models\ContributionEnvelope::query();
        if ($responsible_id) {
            $query->where('responsible_id', $responsible_id);
        }
        $envelopes = $query
            ->select([
                'id',
                'responsible_id',
                'recipient_id',
                'earnings',
                'status',
            ])
            ->with(['recipient.detail', 'responsible.detail'])
            ->orderBy('responsible_id', 'asc')
            ->get();

        // Group by year_week
        $grouped = $envelopes->groupBy('responsible_id')->map(function ($items) {
            return [
                'responsible' => $items->first()->responsible?->detail?->first_name . ' ' . $items->first()->responsible?->detail?->last_name,
                'responsible_id' => $items->first()->responsible?->id,
                'phone_responsible' => $items->first()->responsible?->detail?->phone_numbers ?
                    implode(' / ', gettype($items->first()->responsible?->detail?->phone_numbers) === "string" ? json_decode($items->first()->responsible?->detail?->phone_numbers) :
                        $items->first()->responsible?->detail?->phone_numbers) : $items->first()->responsible?->detail?->phone,
                'envelopes' => $items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'destinataire' => $item->recipient?->detail?->first_name . ' ' . $item->recipient?->detail?->last_name,
                        'status' => $item->status,
                        'montant' => $item->earnings,
                        'envelope_id' => $item->id,
                        'phone_recipient' => $item->recipient?->detail?->phone_numbers ?
                            implode(' / ', gettype($item->recipient?->detail?->phone_numbers) === "string" ? json_decode($item->recipient?->detail?->phone_numbers) :
                                $item->recipient?->detail?->phone_numbers) : $item->recipient?->detail?->phone,
                    ];
                })->values()->toArray(),
            ];
        })->values()->toArray();

        return response()->json($grouped);
    }
}
