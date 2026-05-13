<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ContactMessage;

class ContactMessageSeeder extends Seeder
{
    public function run(): void
    {
        ContactMessage::truncate();

        $messages = [
            ['first_name' => 'Ahmed',    'last_name' => 'Ben Ali',    'email' => 'ahmed.benali@gmail.com',    'phone' => '+216 22 345 678', 'subject' => 'Partnership inquiry',           'message' => 'Hello, I own a camping center in Tabarka and would like to discuss a partnership with your platform.', 'status' => 'unread'],
            ['first_name' => 'Sophie',   'last_name' => 'Martin',     'email' => 'sophie.martin@outlook.fr',  'phone' => null,              'subject' => 'Issue with my reservation',      'message' => 'I made a reservation for 3 nights at Lake Camp but I cannot find it in my profile. Please help.', 'status' => 'unread'],
            ['first_name' => 'Karim',    'last_name' => 'Sfaxi',      'email' => 'k.sfaxi@yahoo.com',         'phone' => '+216 55 987 654', 'subject' => 'Guide certification question',   'message' => 'I am a certified mountain guide and I would like to know the requirements to register on your platform.', 'status' => 'read'],
            ['first_name' => 'Emma',     'last_name' => 'Wilson',     'email' => 'emma.wilson@gmail.com',     'phone' => '+44 7700 900123', 'subject' => 'Feature request',               'message' => 'It would be great to have an offline map feature in the mobile app for areas with no connectivity.', 'status' => 'unread'],
            ['first_name' => 'Mohamed',  'last_name' => 'Trabelsi',   'email' => 'med.trabelsi@email.tn',     'phone' => '+216 98 123 456', 'subject' => 'Payment refund request',         'message' => 'My trip was cancelled due to bad weather but I have not received my refund after 2 weeks. Order #8821.', 'status' => 'read'],
            ['first_name' => 'Fatima',   'last_name' => 'Zahra',      'email' => 'fatima.z@hotmail.com',      'phone' => null,              'subject' => 'Translation issue',              'message' => 'The Arabic translation on some pages is incorrect. I noticed several grammatical errors in the zone descriptions.', 'status' => 'unread'],
            ['first_name' => 'Lucas',    'last_name' => 'Dupont',     'email' => 'lucas.dupont@free.fr',      'phone' => '+33 6 12 34 56 78', 'subject' => 'Cannot login to my account',  'message' => 'Since yesterday I cannot login. I tried reset password but did not receive the email. Username: l.dupont', 'status' => 'read'],
            ['first_name' => 'Nour',     'last_name' => 'Gharbi',     'email' => 'nour.gharbi@icloud.com',    'phone' => '+216 71 234 567', 'subject' => 'Supplier account upgrade',       'message' => 'I would like to upgrade my supplier account to feature more than 10 products. Is there a premium plan?', 'status' => 'unread'],
            ['first_name' => 'James',    'last_name' => 'Robertson',  'email' => 'j.robertson@gmail.com',     'phone' => '+1 555 234 5678', 'subject' => 'Accessibility concern',          'message' => 'I am visually impaired and some parts of your website are not compatible with screen readers.', 'status' => 'unread'],
            ['first_name' => 'Yasmine',  'last_name' => 'Bouaziz',    'email' => 'yasmine.b@gmail.com',       'phone' => '+216 29 876 543', 'subject' => 'Group booking discount',         'message' => 'We are a group of 25 people planning a camping trip next month. Do you offer group discounts?', 'status' => 'read'],
        ];

        foreach ($messages as $index => $data) {
            ContactMessage::create(array_merge($data, [
                'created_at' => now()->subDays(rand(0, 20)),
                'updated_at' => now(),
            ]));
        }

        $this->command->info(count($messages) . ' contact messages created.');
    }
}
