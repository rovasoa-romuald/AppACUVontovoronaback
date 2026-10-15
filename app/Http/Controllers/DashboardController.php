<?php

namespace App\Http\Controllers;

use App\Models\ContributionEnvelope;
use Illuminate\Http\Request;
use App\Models\User;

class DashboardController extends Controller
{
    public function getMainData()
    {
        $data = [];
        $data[] = [
            "label" => "Total des anciens:",
            "value" => User::whereHas("roles", function ($query) {
                $query->where("name", "ancien");
            })->count()
        ];
        $data[] = [
            "label" => "Total des envelopes des anciens avec responable:",
            "color" => "green",
            "value" => User::whereHas("roles", function ($query) {
                $query->where("name", "ancien");
            })->whereHas("jubileeEnvelopes")->count()
        ];
        $data[] = [
            "label" => "Total des envelopes des anciens sans responable:",
            "color" => "red",
            "value" => User::whereHas("roles", function ($query) {
                $query->where("name", "ancien");
            })->whereDoesntHave("jubileeEnvelopes")->count()
        ];
        $data[] = [
            "label" => "Total des enveloppes en attente de prise de contact:",
            "value" =>  ContributionEnvelope::where("status", "to_be_delivered")->count()
        ];
        $data[] = [
            "label" => "Total de destinataire d'enveloppe contacté:",
            "value" => ContributionEnvelope::where("status", "contacted")->count()
        ];
        $data[] = [
            "label" => "Total des enveloppes en attente de livraison \"à la main\":",
            "color" => "red",
            "value" => ContributionEnvelope::whereIn("status", ["contacted", "printed"])->where("mean_of_delivery", "one_to_one")->count()
        ];
        $data[] = [
            "label" => "Nombre des enveloppes livrées à la main:",
            "value" =>  ContributionEnvelope::where("status", "delivered")->where("mean_of_delivery", "one_to_one")->count()
        ];
        $data[] = [
            "label" => "Nombre des enveloppes livrées par email:",
            "value" =>  ContributionEnvelope::where("status", "delivered")->where("mean_of_delivery", "email")->count()
        ];
        $data[] = [
            "label" => "Somme cummulée par cash:",
            "color" => "blue",
            "value" =>  ContributionEnvelope::where("payment_method", "cash")->sum('earnings') . " AR"
        ];
        $data[] = [
            "label" => "Somme cummulée par mobile money:",
            "color" => "blue",
            "value" =>  ContributionEnvelope::where("payment_method", "mobile_money")->sum('earnings') . " AR"
        ];
        $data[] = [
            "label" => "Somme totale cummulée:",
            "color" => "green",
            "value" =>  ContributionEnvelope::sum('earnings') . " AR"
        ];
        return response()->json($data);
    }
}
