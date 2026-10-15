<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\SenderSMS;
use Illuminate\Http\Request;
use App\Models\TemplateMessage;
use Illuminate\Database\Eloquent\Casts\Json;

class SMSController extends Controller
{
    public function getSMSTemplates()
    {
        $templates =  TemplateMessage::where('theme', '40e')->get()->map(
            function ($template) {
                return [
                    'id' => $template->id,
                    "subject" => $template->subject,
                    "content" => $template->content,
                    "tags" => gettype($template->tags) === 'string' ? json_decode($template->tags) : ($template->tags)
                ];
            }
        )->toArray();
        return response()->json($templates);
    }

    public function send(Request $request)
    {
        $request->validate([
            'receiver' => 'required|regex:/^3[24378]\d{7}$/',
            'content' => 'required',
            'templateId' => 'nullable',
            'toUserId' => 'nullable|exists:users,id',
        ], [
            'receiver.regex' => 'Le numéro de téléphone est incorrect! Entrer le de la forme 341234567',
        ]);

        $response = SenderSMS::sendSMS($request->receiver, $request->content, User::find($request->toUserId), TemplateMessage::find($request->templateId));

        return response()->json($response);
    }

    public function getBalanceSMS()
    {
        $response = SenderSMS::getBalanceSMS();
        return !!$response ? response()->json($response) : response()->json(['error' => 'Failed to get SMS balance'], 500);
    }

    public function getSMSTemplate40e($id)
    {
        try {
            $template = TemplateMessage::where('id', $id)->first();
            if (!$template) {
                return response()->json(['error' => 'Template not found'], 404);
            }
            return response()->json($template);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function createSMSTemplate40e(Request $request)
    {
        $request->validate([
            'subject' => 'required|string',
            'content' => 'required|string',
            'tags' => 'nullable|array',
        ]);

        try {
            $template = TemplateMessage::create([
                'theme' => "40e",
                'subject' => $request->subject,
                'content' => $request->content,
                'tags' => json_encode($request->tags),
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }

        return response()->json([
            'id' => $template->id,
            "subject" => $template->subject,
            "content" => $template->content,
            "tags" => gettype($template->tags) === 'string' ? json_decode($template->tags) : ($template->tags)
        ], 201);
    }

    public function updateSMSTemplate40e(Request $request, $id)
    {
        $request->validate([
            'subject' => 'required|string',
            'content' => 'required|string',
            'tags' => 'nullable|array',
        ]);

        try {
            $template = TemplateMessage::where('id', $id)->first();
            if (!$template) {
                return response()->json(['error' => 'Template not found'], 404);
            }
            $template->update([
                'theme' => "40e",
                'subject' => $request->subject,
                'content' => $request->content,
                'tags' => $request->tags,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }

        return response()->json([
            'id' => $template->id,
            "subject" => $template->subject,
            "content" => $template->content,
            "tags" => gettype($template->tags) === 'string' ? json_decode($template->tags) : ($template->tags)
        ]);
    }

    public function deleteSMSTemplate40e($id)
    {
        try {
            $template = TemplateMessage::where('id', $id)->first();
            if (!$template) {
                return response()->json(['error' => 'Template not found'], 404);
            }
            $template->delete();
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }

        return response()->json(['message' => 'Template deleted successfully']);
    }
}
