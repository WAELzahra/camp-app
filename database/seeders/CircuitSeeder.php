<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Circuit;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CircuitSeeder extends Seeder
{
    /**
     * Tunisian circuits data with real locations
     */
    private $circuits = [
        // Northern Tunisia - Coastal & Mountain circuits
        [
            'adresse_debut_circuit' => 'Sidi Bou Said, Tunis',
            'adresse_fin_circuit' => 'Carthage, Tunis',
            'description' => 'Coastal path from the blue and white village of Sidi Bou Said to the ancient Punic and Roman ruins of Carthage. Stunning Mediterranean views throughout.',
            'distance_km' => 8.5,
            'estimation_temps' => 2.5,
            'difficulty' => 'facile',
            'danger_level' => 'low',
        ],
        [
            'adresse_debut_circuit' => 'Bizerte Corniche',
            'adresse_fin_circuit' => 'Cap Angela, Bizerte',
            'description' => 'Scenic coastal hike to the northernmost point of Africa. Beautiful cliffs and sea views.',
            'distance_km' => 12.0,
            'estimation_temps' => 4.0,
            'difficulty' => 'moyenne',
            'danger_level' => 'moderate',
        ],
        [
            'adresse_debut_circuit' => 'Ichkeul National Park Entrance',
            'adresse_fin_circuit' => 'Lake Ichkeul Viewpoint',
            'description' => 'UNESCO World Heritage site. Trail through wetlands and forests, perfect for bird watching.',
            'distance_km' => 15.5,
            'estimation_temps' => 5.0,
            'difficulty' => 'moyenne',
            'danger_level' => 'low',
        ],

        // Cap Bon Peninsula
        [
            'adresse_debut_circuit' => 'Kerkouane Ruins',
            'adresse_fin_circuit' => 'Cap Bon Lighthouse',
            'description' => 'Historical hike along the Cap Bon coast, passing through Punic ruins and orange groves.',
            'distance_km' => 10.2,
            'estimation_temps' => 3.5,
            'difficulty' => 'facile',
            'danger_level' => 'low',
        ],
        [
            'adresse_debut_circuit' => 'Hammamet Medina',
            'adresse_fin_circuit' => 'Yasmine Hammamet Marina',
            'description' => 'Coastal walk through the old medina to the modern marina, passing beautiful beaches.',
            'distance_km' => 6.8,
            'estimation_temps' => 2.0,
            'difficulty' => 'facile',
            'danger_level' => 'low',
        ],

        // Central Tunisia - Mountain & Desert transitions
        [
            'adresse_debut_circuit' => 'Zaghouan Roman Temple',
            'adresse_fin_circuit' => 'Zaghouan Peak',
            'description' => 'Mountain trail from the Roman Water Temple to the summit of Jebel Zaghouan.',
            'distance_km' => 9.5,
            'estimation_temps' => 4.5,
            'difficulty' => 'difficile',
            'danger_level' => 'high',
        ],
        [
            'adresse_debut_circuit' => 'Kairouan Medina',
            'adresse_fin_circuit' => 'Aghlabid Basins',
            'description' => 'Cultural circuit through the holy city of Kairouan, visiting mosques and ancient water reservoirs.',
            'distance_km' => 5.2,
            'estimation_temps' => 2.0,
            'difficulty' => 'facile',
            'danger_level' => 'low',
        ],
        [
            'adresse_debut_circuit' => 'Sbeitla Forum',
            'adresse_fin_circuit' => 'Sbeitla Capitoline Temples',
            'description' => 'Walk through the best-preserved Roman ruins in Tunisia, with three standing temples.',
            'distance_km' => 4.5,
            'estimation_temps' => 1.5,
            'difficulty' => 'facile',
            'danger_level' => 'low',
        ],

        // Saharan Tunisia - Desert circuits
        [
            'adresse_debut_circuit' => 'Douz - Gate of the Sahara',
            'adresse_fin_circuit' => 'Ksar Ghilane Oasis',
            'description' => 'Classic desert circuit through sand dunes to a hot spring oasis. Camel trekking route.',
            'distance_km' => 45.0,
            'estimation_temps' => 8.0,
            'difficulty' => 'difficile',
            'danger_level' => 'extreme',
        ],
        [
            'adresse_debut_circuit' => 'Matmata Cave Houses',
            'adresse_fin_circuit' => 'Tamezret Berber Village',
            'description' => 'Circuit through traditional Berber cave dwellings and mountain villages.',
            'distance_km' => 18.5,
            'estimation_temps' => 6.0,
            'difficulty' => 'moyenne',
            'danger_level' => 'moderate',
        ],
        [
            'adresse_debut_circuit' => 'Chenini Berber Village',
            'adresse_fin_circuit' => 'Guermassa Hilltop Village',
            'description' => 'Mountain circuit in the Djebel Dahar region, visiting ancient granaries and cliff villages.',
            'distance_km' => 12.8,
            'estimation_temps' => 4.5,
            'difficulty' => 'moyenne',
            'danger_level' => 'moderate',
        ],

        // Chott el Djerid region
        [
            'adresse_debut_circuit' => 'Tozeur Old Town',
            'adresse_fin_circuit' => 'Chebika Mountain Oasis',
            'description' => 'Circuit through the desert oasis and canyons made famous by Star Wars films.',
            'distance_km' => 25.0,
            'estimation_temps' => 5.0,
            'difficulty' => 'moyenne',
            'danger_level' => 'moderate',
        ],
        [
            'adresse_debut_circuit' => 'Tamerza Waterfall',
            'adresse_fin_circuit' => 'Mides Canyon',
            'description' => 'Spectacular canyon circuit with waterfalls and dramatic rock formations.',
            'distance_km' => 16.5,
            'estimation_temps' => 5.5,
            'difficulty' => 'difficile',
            'danger_level' => 'high',
        ],

        // Coastal Tunisia - South
        [
            'adresse_debut_circuit' => 'Sfax Medina',
            'adresse_fin_circuit' => 'Sfax Port',
            'description' => 'Urban circuit through the economic capital, visiting traditional souks and the fishing port.',
            'distance_km' => 4.2,
            'estimation_temps' => 1.5,
            'difficulty' => 'facile',
            'danger_level' => 'low',
        ],
        [
            'adresse_debut_circuit' => 'Djerba Houmt Souk',
            'adresse_fin_circuit' => 'El Ghriba Synagogue',
            'description' => 'Cultural circuit on the island of Djerba, visiting the oldest synagogue in Africa.',
            'distance_km' => 9.0,
            'estimation_temps' => 3.0,
            'difficulty' => 'facile',
            'danger_level' => 'low',
        ],
        [
            'adresse_debut_circuit' => 'Zarzis Beach',
            'adresse_fin_circuit' => 'Boughrara Lagoon',
            'description' => 'Coastal circuit along pristine Mediterranean beaches and lagoons.',
            'distance_km' => 14.0,
            'estimation_temps' => 4.0,
            'difficulty' => 'facile',
            'danger_level' => 'low',
        ],

        // Mountain circuits - Dorsale Mountains
        [
            'adresse_debut_circuit' => 'Ain Draham Forest',
            'adresse_fin_circuit' => 'Ain Draham Summit',
            'description' => 'Cork oak forest circuit in the Kroumirie mountains, the greenest part of Tunisia.',
            'distance_km' => 11.5,
            'estimation_temps' => 4.0,
            'difficulty' => 'moyenne',
            'danger_level' => 'moderate',
        ],
        [
            'adresse_debut_circuit' => 'Bulla Regia Ruins',
            'adresse_fin_circuit' => 'Jebel el-Abiod Viewpoint',
            'description' => 'Circuit combining Roman underground villas with mountain views.',
            'distance_km' => 8.0,
            'estimation_temps' => 3.0,
            'difficulty' => 'facile',
            'danger_level' => 'low',
        ],

        // Additional circuits
        [
            'adresse_debut_circuit' => 'El Kef Kasbah',
            'adresse_fin_circuit' => 'El Kef Old Town',
            'description' => 'Historical circuit through the mountaintop city with Byzantine and Ottoman architecture.',
            'distance_km' => 3.5,
            'estimation_temps' => 1.5,
            'difficulty' => 'facile',
            'danger_level' => 'low',
        ],
        [
            'adresse_debut_circuit' => 'Korbous Hot Springs',
            'adresse_fin_circuit' => 'Cap Zbib',
            'description' => 'Coastal circuit with natural hot springs flowing into the sea.',
            'distance_km' => 7.2,
            'estimation_temps' => 2.5,
            'difficulty' => 'facile',
            'danger_level' => 'moderate',
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Disable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        
        // Truncate the table
        Circuit::truncate();
        
        // Re-enable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        foreach ($this->circuits as $circuit) {
            Circuit::create([
                'adresse_debut_circuit' => $circuit['adresse_debut_circuit'],
                'adresse_fin_circuit' => $circuit['adresse_fin_circuit'],
                'description' => $circuit['description'],
                'distance_km' => $circuit['distance_km'],
                'estimation_temps' => $circuit['estimation_temps'],
                'difficulty' => $circuit['difficulty'],
                'danger_level' => $circuit['danger_level'],
                'created_at' => Carbon::now()->subDays(rand(1, 60)),
                'updated_at' => Carbon::now(),
            ]);
        }

        $this->command->info('✅ ' . count($this->circuits) . ' Tunisian circuits seeded successfully!');
    }
}