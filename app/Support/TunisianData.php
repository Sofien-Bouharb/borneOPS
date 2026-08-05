<?php

namespace App\Support;

class TunisianData
{
    public const LAT_MIN = 30.230;
    public const LAT_MAX = 37.548;
    public const LON_MIN = 7.524;
    public const LON_MAX = 11.598;

    public const CITIES = [
        ['name' => 'Tunis', 'postal_code' => '1000'],
        ['name' => 'Ariana', 'postal_code' => '2080'],
        ['name' => 'Ben Arous', 'postal_code' => '2013'],
        ['name' => 'La Manouba', 'postal_code' => '2010'],
        ['name' => 'Nabeul', 'postal_code' => '8000'],
        ['name' => 'Zaghouan', 'postal_code' => '1100'],
        ['name' => 'Bizerte', 'postal_code' => '7000'],
        ['name' => 'Béja', 'postal_code' => '9000'],
        ['name' => 'Jendouba', 'postal_code' => '8100'],
        ['name' => 'Le Kef', 'postal_code' => '7100'],
        ['name' => 'Siliana', 'postal_code' => '6100'],
        ['name' => 'Kairouan', 'postal_code' => '3100'],
        ['name' => 'Kasserine', 'postal_code' => '1200'],
        ['name' => 'Sidi Bouzid', 'postal_code' => '9100'],
        ['name' => 'Sousse', 'postal_code' => '4000'],
        ['name' => 'Monastir', 'postal_code' => '5000'],
        ['name' => 'Mahdia', 'postal_code' => '5100'],
        ['name' => 'Sfax', 'postal_code' => '3000'],
        ['name' => 'Gafsa', 'postal_code' => '2100'],
        ['name' => 'Tozeur', 'postal_code' => '2200'],
        ['name' => 'Kebili', 'postal_code' => '4200'],
        ['name' => 'Gabès', 'postal_code' => '6000'],
        ['name' => 'Médenine', 'postal_code' => '4100'],
        ['name' => 'Tataouine', 'postal_code' => '3200'],
    ];

    public const STREET_NAMES = [
        'Avenue Habib Bourguiba',
        'Avenue Mohamed V',
        'Rue de Marseille',
        'Rue Ibn Khaldoun',
        'Rue de la Liberté',
        'Avenue Farhat Hached',
        'Rue Charles de Gaulle',
        'Rue de Palestine',
        'Avenue Taïeb Mhiri',
        'Rue Ali Belhouane',
        'Rue du 7 Novembre',
        'Avenue de la République',
    ];

    public const ORGANIZATION_NAMES = [
        'Carthage Energy',
        'Numidia Power',
        'El Jazira Charge',
        'Tunisie Verte Énergie',
        'Medina Mobility',
        'Sahel Elec',
        'Djerba Green Power',
        'Atlas Charge',
        'Zitouna Energy',
        'Kairouan Mobilité Électrique',
        'Sfax Power Solutions',
        'Bizerte Énergies Nouvelles',
    ];

    public const ORGANIZATION_SUFFIXES = ['SARL', 'SA', 'SUARL'];
}
