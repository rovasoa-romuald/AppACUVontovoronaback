<?php

namespace Database\Seeders;

use App\Models\Level;
use App\Models\Major;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class LevelMajorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $majors = [
            [
                "name" => "Batiments et Travaux Publics",
                "abbreviation" => "BTP"
            ],
            [
                "name" => "Hydraulique",
                "abbreviation" => "HYD"
            ],
            [
                "name" => "Information Géographique et Aménagement du Territoire",
                "abbreviation" => "IGAT"
            ],
            [
                "name" => "Génie Géologique",
                "abbreviation" => "GGEO"
            ],
            [
                "name" => "Ingénierie Minières",
                "abbreviation" => "IMIN"
            ],
            [
                "name" => "Ingénierie pétrlière",
                "abbreviation" => "IPE"
            ],
            [
                "name" => "Météorologie",
                "abbreviation" => "MTO"
            ],
            [
                "name" => "Electronique",
                "abbreviation" => "EN"
            ],
            [
                "name" => "Télécommunication",
                "abbreviation" => "TCO"
            ],
            [
                "name" => "Génie des Procédés Chimiques et Industriels",
                "abbreviation" => "GPCI"
            ],
            [
                "name" => "Génie Mécanique et Industriel",
                "abbreviation" => "GMI"
            ],
            [
                "name" => "Génie Eléctrique",
                "abbreviation" => "GE"
            ],
            [
                "name" => "Génie Rurale",
                "abbreviation" => "GER"
            ],
            [
                "name" => "Science et Ingénierie de Matériaux",
                "abbreviation" => "SIM"
            ]
        ];

        foreach ($majors as $major) {
            $majorItem = Major::where('abbreviation', $major['abbreviation'])->first();
            if (! $majorItem) {
                Major::create($major);
            }
        }

        $levels = [
            [
                "name" => "Licence Académique - Première Année",
                "abbreviation" => "L1"
            ],
            [
                "name" => "Licence Académique - Deuxième Année",
                "abbreviation" => "L2"
            ],
            [
                "name" => "Licence Académique - Troisième Année",
                "abbreviation" => "L3"
            ],
            [
                "name" => "Master Académique - Quatrième Année",
                "abbreviation" => "M1"
            ],
            [
                "name" => "Master Académique - Cinquième Année",
                "abbreviation" => "M2"
            ],
            [
                "name" => "Licence Professionnelle - Première Année",
                "abbreviation" => "LP1"
            ],
            [
                "name" => "Licence Professionnelle - Deuxième Année",
                "abbreviation" => "LP2"
            ],
            [
                "name" => "Licence Professionnelle - Troisième Année",
                "abbreviation" => "LP3"
            ],
            [
                "name" => "Master Professionnelle - Quatrième Année",
                "abbreviation" => "MP1"
            ],
            [
                "name" => "Master Professionnelle - Cinquième Année",
                "abbreviation" => "MP2"
            ],
            [
                "name" => "Licence Professionnelle - Première Année - Luban",
                "abbreviation" => "LP1L"
            ],
            [
                "name" => "Licence Professionnelle - Deuxième Année - Luban",
                "abbreviation" => "LP2L"
            ],
            [
                "name" => "Licence Professionnelle - Troisième Année - Luban",
                "abbreviation" => "LP3L"
            ],
            [
                "name" => "Master Professionnelle - Quatrième Année - Luban",
                "abbreviation" => "MP1L"
            ],
            [
                "name" => "Master Professionnelle - Cinquième Année - Luban",
                "abbreviation" => "MP2L"
            ]
        ];

        foreach ($levels as $level) {
            $levelItem = Level::where('abbreviation', $major['abbreviation'])->first();
            if (! $levelItem) {
                Level::create($level);
            }
        }
    }
}
