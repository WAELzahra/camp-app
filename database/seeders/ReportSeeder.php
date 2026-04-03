<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Report;
use App\Models\User;

class ReportSeeder extends Seeder
{
    public function run(): void
    {
        Report::truncate();

        $users = User::pluck('id')->toArray();
        if (empty($users)) {
            $this->command->warn('No users found.');
            return;
        }

        $types    = ['user', 'center', 'group', 'supplier', 'zone', 'platform'];
        $statuses = ['pending', 'reviewing', 'resolved'];

        // report_type enum: bug | suspicious_user | safety_concern | other
        $reports = [
            ['target_type' => 'user',     'report_type' => 'suspicious_user', 'priority' => 'medium', 'status' => 'pending',   'subject' => 'Inappropriate behavior',          'description' => 'This user has been harassing other campers repeatedly during the trip.'],
            ['target_type' => 'zone',     'report_type' => 'safety_concern',  'priority' => 'high',   'status' => 'pending',   'subject' => 'Dangerous path near river',        'description' => 'The trail near the river is extremely slippery and has no safety barriers.'],
            ['target_type' => 'platform', 'report_type' => 'bug',             'priority' => 'low',    'status' => 'reviewing', 'subject' => 'Payment not processed',            'description' => 'I was charged twice for the same reservation and need a refund.'],
            ['target_type' => 'center',   'report_type' => 'other',           'priority' => 'medium', 'status' => 'resolved',  'subject' => 'Facilities not as described',      'description' => 'The shower facilities shown in photos were not available during our stay.'],
            ['target_type' => 'supplier', 'report_type' => 'other',           'priority' => 'low',    'status' => 'pending',   'subject' => 'Equipment quality issue',          'description' => 'The tent rented had multiple holes and was not suitable for camping.'],
            ['target_type' => 'group',    'report_type' => 'suspicious_user', 'priority' => 'medium', 'status' => 'reviewing', 'subject' => 'Group organizer unresponsive',     'description' => 'The group organizer stopped responding after payment was made.'],
            ['target_type' => 'user',     'report_type' => 'suspicious_user', 'priority' => 'high',   'status' => 'pending',   'subject' => 'Fraudulent profile',              'description' => 'This guide appears to be using fake certifications and reviews.'],
            ['target_type' => 'zone',     'report_type' => 'safety_concern',  'priority' => 'high',   'status' => 'pending',   'subject' => 'Wildlife sighting - safety risk',  'description' => 'A group of wild boars was spotted near the campsite entrance.'],
            ['target_type' => 'platform', 'report_type' => 'bug',             'priority' => 'low',    'status' => 'resolved',  'subject' => 'App crash on booking page',        'description' => 'The mobile app crashes when trying to finalize a reservation.'],
            ['target_type' => 'center',   'report_type' => 'other',           'priority' => 'medium', 'status' => 'pending',   'subject' => 'Unauthorized price increase',      'description' => 'The center charged 40% more than the listed price without warning.'],
            ['target_type' => 'supplier', 'report_type' => 'other',           'priority' => 'low',    'status' => 'reviewing', 'subject' => 'Late delivery of equipment',       'description' => 'Equipment was delivered 2 days late ruining our camping trip.'],
            ['target_type' => 'user',     'report_type' => 'suspicious_user', 'priority' => 'medium', 'status' => 'resolved',  'subject' => 'Fake reviews posted',             'description' => 'This user seems to be posting fake positive reviews for their own services.'],
        ];

        foreach ($reports as $data) {
            $reporterIdx  = array_rand($users);
            $reportedIdx  = array_rand($users);
            while ($reportedIdx === $reporterIdx) $reportedIdx = array_rand($users);

            Report::create([
                'reporter_user_id' => $users[$reporterIdx],
                'reported_user_id' => $data['target_type'] === 'user' ? $users[$reportedIdx] : null,
                'target_type'      => $data['target_type'],
                'report_type'      => $data['report_type'],
                'target_id'        => null,
                'location_lat'     => $data['target_type'] === 'zone' ? round(33.0 + (rand() / getrandmax()) * 3, 5) : null,
                'location_lng'     => $data['target_type'] === 'zone' ? round(9.0  + (rand() / getrandmax()) * 3, 5) : null,
                'subject'          => $data['subject'],
                'description'      => $data['description'],
                'page_url'         => null,
                'screenshot_path'  => null,
                'status'           => $data['status'],
                'priority'         => $data['priority'],
                'admin_note'       => $data['status'] === 'resolved' ? 'Verified and resolved by admin team.' : null,
                'created_at'       => now()->subDays(rand(0, 30)),
                'updated_at'       => now(),
            ]);
        }

        $this->command->info(count($reports) . ' reports created.');
    }
}
