<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Balance;
use App\Models\WithdrawalRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class BalanceWithdrawalSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        WithdrawalRequest::truncate();
        Balance::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $users = User::select('id', 'first_name', 'last_name')->get();

        if ($users->isEmpty()) {
            $this->command->warn('No users found.');
            return;
        }

        // Créer des balances réalistes pour plusieurs utilisateurs
        $balancesData = [
            // user_idx, solde_disponible, solde_en_attente, total_encaisse, total_retire, total_rembourse
            [0,  1250.00,  0.00,  1800.00,  550.00,   0.00],
            [1,   480.50,  0.00,   750.00,  269.50,   0.00],
            [2,  2100.00, 90.00,  2800.00,  700.00,  90.00],
            [3,   320.00,  0.00,   500.00,  180.00,   0.00],
            [4,  3540.00,  0.00,  4200.00,  660.00,   0.00],
            [5,    75.00,  0.00,   300.00,  225.00,   0.00],
            [6,  1870.50,  0.00,  2100.00,  229.50,   0.00],
        ];

        $createdUsers = [];
        foreach ($balancesData as [$idx, $dispo, $attente, $encaisse, $retire, $rembourse]) {
            if (! isset($users[$idx])) continue;
            $userId = $users[$idx]->id;

            Balance::create([
                'user_id'            => $userId,
                'solde_disponible'   => $dispo,
                'solde_en_attente'   => $attente,
                'total_encaisse'     => $encaisse,
                'total_retire'       => $retire,
                'total_rembourse'    => $rembourse,
                'dernier_mouvement_at' => now()->subDays(rand(0, 15)),
            ]);
            $createdUsers[] = $userId;
        }

        // Demandes de retrait
        $withdrawalsData = [
            // user_idx, montant, status, methode, days_ago
            [0,  500.00, 'complété',   'virement_bancaire', 20],
            [0,  350.00, 'en_attente', 'virement_bancaire',  2],
            [1,  200.00, 'approuvé',   'flouci',             5],
            [2,  700.00, 'complété',   'virement_bancaire', 30],
            [2,  200.00, 'en_attente', 'chèque',             1],
            [3,  180.00, 'rejeté',     'virement_bancaire', 10],
            [4,  660.00, 'complété',   'virement_bancaire', 25],
            [4,  400.00, 'en_cours',   'virement_bancaire',  3],
            [6,  229.50, 'en_attente', 'flouci',             1],
        ];

        foreach ($withdrawalsData as [$idx, $montant, $status, $methode, $daysAgo]) {
            if (! isset($users[$idx])) continue;
            $userId = $users[$idx]->id;

            WithdrawalRequest::create([
                'user_id'          => $userId,
                'montant'          => $montant,
                'status'           => $status,
                'methode'          => $methode,
                'details_paiement' => $methode === 'virement_bancaire' ? [
                    'banque'        => 'STB Bank',
                    'rib'           => 'TN59 0801 0000 ' . rand(1000, 9999) . ' ' . rand(1000, 9999) . ' ' . rand(10, 99),
                    'beneficiaire'  => $users[$idx]->first_name . ' ' . $users[$idx]->last_name,
                ] : ($methode === 'flouci' ? [
                    'telephone' => '+216 9' . rand(1000000, 9999999),
                ] : null),
                'admin_note'       => in_array($status, ['complété', 'approuvé']) ? 'Virement effectué — traité par admin.' : (
                    $status === 'rejeté' ? 'Informations bancaires incorrectes.' : null
                ),
                'processed_by'     => in_array($status, ['complété', 'approuvé', 'rejeté', 'en_cours']) ? $users[0]->id : null,
                'processed_at'     => in_array($status, ['complété', 'approuvé', 'rejeté']) ? now()->subDays($daysAgo - 1) : null,
                'created_at'       => now()->subDays($daysAgo),
                'updated_at'       => now()->subDays(max(0, $daysAgo - 1)),
            ]);
        }

        $this->command->info(count($balancesData) . ' balances et ' . count($withdrawalsData) . ' demandes de retrait créées.');
    }
}
