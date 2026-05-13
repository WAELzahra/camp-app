<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Annonce;
use App\Models\AnnonceLike;
use App\Models\Comment;
use App\Models\CommentLike;
use App\Models\Photo;
use Carbon\Carbon;

class AnnonceSeeder extends Seeder
{
    /**
     * Users from UserSeeder — kept here for clarity
     *
     * ID 1  → Admin          (role 6) — does not post annonces
     * ID 2  → Center Manager (role 3) — posts annonces ✅
     * ID 3  → DeadXShot      (role 1) — camper, likes & comments
     * ID 4  → Nejikh         (role 2) — group,  posts annonces ✅
     * ID 5  → Forest Rangers  (role 2) — group,  posts annonces ✅
     * ID 6  → Coastal Adv.   (role 2) — group,  posts annonces ✅
     * ID 7  → Camp Equipment  (role 4) — supplier, likes & comments
     * ID 8  → Ahmed Guide     (role 5) — guide,  likes & comments
     * ID 9  → Sarah           (role 1) — camper, likes & comments
     * ID 10 → Mike            (role 1) — camper, likes & comments
     */

    // Users who CREATE annonces (centers + groups)
    private array $publishers = [2, 4, 5, 6];

    // Users who LIKE and COMMENT (campers, guides, suppliers)
    private array $audience = [3, 7, 8, 9, 10];

    public function run(): void
    {
        $this->command->info('Seeding annonces...');

        // ── Annonces ──────────────────────────────────────────────────────────
        $annoncesData = [
            // ─ Center Manager (ID 2) annonces ─
            [
                'user_id'     => 2,
                'title'       => 'School Camp 2025',
                'description' => 'Educational outdoor experience for students with team-building activities and nature exploration. Students will discover the beauty of Tunisian nature.',
                'type'        => 'School Camp',
                'activities'  => ['Hiking', 'Bird Watching', 'Nature Photography', 'Survival Skills', 'First Aid'],
                'address'     => 'Zaghouan, Tunisia',
                'latitude'    => '36.4029',
                'longitude'   => '10.1422',
                'start_date'  => Carbon::now()->addDays(20),
                'end_date'    => Carbon::now()->addDays(23),
                'status'      => 'approved',
                'views_count' => 23,
            ],
            [
                'user_id'     => 2,
                'title'       => 'Kids Summer Camp',
                'description' => 'Fun-filled adventure program designed for children with supervised activities and safety measures in place at all times.',
                'type'        => 'Summer Camp',
                'activities'  => ['Swimming', 'Camping', 'Bonfire', 'BBQ', 'Frisbee'],
                'address'     => 'Hammamet, Tunisia',
                'latitude'    => '36.3997',
                'longitude'   => '10.6167',
                'start_date'  => Carbon::now()->addDays(10),
                'end_date'    => Carbon::now()->addDays(33),
                'status'      => 'approved',
                'views_count' => 42,
            ],
            [
                'user_id'     => 2,
                'title'       => 'Astronomy Night Camp',
                'description' => 'Spend nights under the stars with professional telescopes and astronomy guides. Learn about constellations and deep sky objects.',
                'type'        => 'Astronomy Night',
                'activities'  => ['Stargazing', 'Camping', 'Sunset Watching', 'Meditation'],
                'address'     => 'Matmata, Tunisia',
                'latitude'    => '33.5444',
                'longitude'   => '9.9706',
                'start_date'  => Carbon::now()->addDays(40),
                'end_date'    => Carbon::now()->addDays(41),
                'status'      => 'approved',
                'views_count' => 33,
            ],

            // ─ Nejikh Group (ID 4) annonces ─
            [
                'user_id'     => 4,
                'title'       => 'Mountain Escape Weekend',
                'description' => 'Weekend camping adventure in the mountains with hiking and survival skills workshops for beginners and experienced campers alike.',
                'type'        => 'Adventure Expedition',
                'activities'  => ['Hiking', 'Rock Climbing', 'Survival Skills', 'Stargazing', 'Knot Tying'],
                'address'     => 'Jebel Zaghouan, Tunisia',
                'latitude'    => '36.3830',
                'longitude'   => '10.1100',
                'start_date'  => Carbon::now()->addDays(45),
                'end_date'    => Carbon::now()->addDays(47),
                'status'      => 'approved',
                'views_count' => 18,
            ],
            [
                'user_id'     => 4,
                'title'       => 'Desert Safari Adventure',
                'description' => 'Explore the breathtaking Tunisian Sahara with expert guides. Camel rides, dune surfing, and stargazing included.',
                'type'        => 'Desert Safari',
                'activities'  => ['Stargazing', 'Sunset Watching', 'Camping', 'Geocaching'],
                'address'     => 'Douz, Tunisia',
                'latitude'    => '33.4500',
                'longitude'   => '9.0167',
                'start_date'  => Carbon::now()->addDays(14),
                'end_date'    => Carbon::now()->addDays(17),
                'status'      => 'approved',
                'views_count' => 55,
            ],
            [
                'user_id'     => 4,
                'title'       => 'Youth Leadership Camp',
                'description' => 'Empowering young leaders through outdoor challenges, team activities, and personal development workshops.',
                'type'        => 'Youth Camp',
                'activities'  => ['Hiking', 'Rock Climbing', 'Survival Skills', 'First Aid', 'Trail Running'],
                'address'     => 'El Kef, Tunisia',
                'latitude'    => '36.1833',
                'longitude'   => '8.7167',
                'start_date'  => Carbon::now()->addDays(25),
                'end_date'    => Carbon::now()->addDays(31),
                'status'      => 'pending',
                'views_count' => 12,
            ],

            // ─ Forest Rangers (ID 5) annonces ─
            [
                'user_id'     => 5,
                'title'       => 'Family Camping Retreat',
                'description' => 'Perfect getaway for families looking to bond in nature with kid-friendly activities suitable for all ages.',
                'type'        => 'Family Camping',
                'activities'  => ['Camping', 'BBQ', 'Bonfire', 'Fishing', 'Yoga'],
                'address'     => 'Ain Draham, Tunisia',
                'latitude'    => '36.7833',
                'longitude'   => '8.6833',
                'start_date'  => Carbon::now()->addDays(5),
                'end_date'    => Carbon::now()->addDays(7),
                'status'      => 'approved',
                'views_count' => 31,
            ],
            [
                'user_id'     => 5,
                'title'       => 'Eco-Friendly Camping',
                'description' => 'Sustainable camping experience focusing on environmental conservation and eco-friendly practices in the wild.',
                'type'        => 'Eco Camp',
                'activities'  => ['Nature Photography', 'Wildlife Viewing', 'Bird Watching', 'Meditation', 'Yoga'],
                'address'     => 'Ichkeul National Park, Tunisia',
                'latitude'    => '37.1667',
                'longitude'   => '9.6667',
                'start_date'  => Carbon::now()->addDays(30),
                'end_date'    => Carbon::now()->addDays(32),
                'status'      => 'approved',
                'views_count' => 35,
            ],
            [
                'user_id'     => 5,
                'title'       => 'Photography in Nature Workshop',
                'description' => 'A unique camp for photography enthusiasts. Capture stunning landscapes, wildlife, and night skies with professional guidance.',
                'type'        => 'Photography Workshop',
                'activities'  => ['Nature Photography', 'Wildlife Viewing', 'Stargazing', 'Hiking', 'Sunset Watching'],
                'address'     => 'Chott el-Jerid, Tunisia',
                'latitude'    => '33.7167',
                'longitude'   => '8.4333',
                'start_date'  => Carbon::now()->addDays(50),
                'end_date'    => Carbon::now()->addDays(53),
                'status'      => 'approved',
                'views_count' => 19,
            ],

            // ─ Coastal Adventures (ID 6) annonces ─
            [
                'user_id'     => 6,
                'title'       => 'Wilderness Survival Camp',
                'description' => 'Learn essential survival skills from experienced guides in a safe and structured environment. Suitable for adults.',
                'type'        => 'Survival Training',
                'activities'  => ['Survival Skills', 'Knot Tying', 'First Aid', 'Map Reading', 'Outdoor Cooking'],
                'address'     => 'Beja, Tunisia',
                'latitude'    => '36.7333',
                'longitude'   => '9.1833',
                'start_date'  => Carbon::now()->addDays(60),
                'end_date'    => Carbon::now()->addDays(62),
                'status'      => 'approved',
                'views_count' => 27,
            ],
            [
                'user_id'     => 6,
                'title'       => 'Beach Camping Weekend',
                'description' => 'Relax and unwind with a beachside camping experience. Perfect for groups of friends looking for sun, sea, and good vibes.',
                'type'        => 'Beach Camping',
                'activities'  => ['Swimming', 'Camping', 'BBQ', 'Bonfire', 'Frisbee', 'Stand-up Paddleboarding'],
                'address'     => 'Tabarka, Tunisia',
                'latitude'    => '36.9547',
                'longitude'   => '8.7583',
                'start_date'  => Carbon::now()->addDays(8),
                'end_date'    => Carbon::now()->addDays(10),
                'status'      => 'approved',
                'views_count' => 48,
            ],
            [
                'user_id'     => 6,
                'title'       => 'Meditation & Yoga Retreat',
                'description' => 'Reconnect with yourself in nature. Daily yoga sessions, guided meditation, and mindfulness workshops in a serene forest setting.',
                'type'        => 'Meditation Retreat',
                'activities'  => ['Yoga', 'Meditation', 'Sunset Watching', 'Nature Photography', 'Hiking'],
                'address'     => 'Siliana Forest, Tunisia',
                'latitude'    => '36.0833',
                'longitude'   => '9.3667',
                'start_date'  => Carbon::now()->addDays(35),
                'end_date'    => Carbon::now()->addDays(38),
                'status'      => 'approved',
                'views_count' => 29,
            ],
        ];

        $createdAnnonces = [];

        foreach ($annoncesData as $data) {
            $annonce = Annonce::create([
                'user_id'        => $data['user_id'],
                'title'          => $data['title'],
                'description'    => $data['description'],
                'type'           => $data['type'],
                'activities'     => $data['activities'],
                'address'        => $data['address'],
                'latitude'       => $data['latitude'],
                'longitude'      => $data['longitude'],
                'start_date'     => $data['start_date'],
                'end_date'       => $data['end_date'],
                'status'         => $data['status'],
                'is_archived'    => false,
                'auto_archive'   => true,
                'views_count'    => $data['views_count'],
                'likes_count'    => 0,
                'comments_count' => 0,
            ]);

            Photo::create([
                'annonce_id'  => $annonce->id,
                'path_to_img' => 'annonces/placeholder_' . $annonce->id . '.jpg',
            ]);

            $createdAnnonces[] = $annonce;
        }

        $this->command->info('Seeded ' . count($createdAnnonces) . ' annonces.');

        // ── AnnonceLikes (only audience users like annonces) ──────────────────
        $this->command->info('Seeding annonce likes...');

        $likesInserted = 0;

        foreach ($createdAnnonces as $annonce) {
            if ($annonce->status !== 'approved') continue;

            // Each approved annonce gets liked by 2–4 audience members
            $likerIds = collect($this->audience)->shuffle()->take(rand(2, 4));

            foreach ($likerIds as $userId) {
                $alreadyLiked = AnnonceLike::where('annonce_id', $annonce->id)
                    ->where('user_id', $userId)
                    ->exists();

                if (!$alreadyLiked) {
                    AnnonceLike::create([
                        'annonce_id' => $annonce->id,
                        'user_id'    => $userId,
                    ]);
                    $likesInserted++;
                }
            }

            // Sync likes_count
            $annonce->update([
                'likes_count' => AnnonceLike::where('annonce_id', $annonce->id)->count(),
            ]);
        }

        $this->command->info("Seeded {$likesInserted} annonce likes.");

        // ── Comments (audience users comment on annonces) ─────────────────────
        $this->command->info('Seeding comments...');

        $sampleComments = [
            "This looks amazing! Can't wait to join.",
            "Is there any discount available for groups?",
            "I went last year and it was absolutely fantastic!",
            "What equipment should we bring along?",
            "Are meals included in the price?",
            "This is exactly what I was looking for. Just signed up!",
            "How many spots are left?",
            "The location looks breathtaking. Highly recommend!",
            "Do you accept kids under 10 years old?",
            "Is transportation provided from Tunis?",
            "Best camping experience I've ever had!",
            "What is the cancellation policy?",
            "Can beginners join this camp or is experience required?",
            "Highly recommend this to everyone looking for adventure!",
            "Is there WiFi or should we go fully off-grid?",
        ];

        $sampleReplies = [
            "Great question! I'll ask the organizer.",
            "Yes, I had the same question before joining.",
            "It was incredible, you won't regret it!",
            "You should check the description for full details.",
            "I believe meals are included — worth confirming though.",
            "Totally agree with this comment!",
            "I think there are still a few spots available.",
            "The photos don't do it justice — it's even better in person!",
        ];

        $commentsCreated = 0;
        $repliesCreated  = 0;

        foreach ($createdAnnonces as $annonce) {
            if ($annonce->status !== 'approved') continue;

            // 2–4 top-level comments per annonce from audience users
            $commentCount  = rand(2, 4);
            $commenterPool = collect($this->audience)->shuffle();

            for ($i = 0; $i < $commentCount; $i++) {
                $commenterId = $commenterPool[$i % count($this->audience)];

                $comment = Comment::create([
                    'user_id'     => $commenterId,
                    'annonce_id'  => $annonce->id,
                    'parent_id'   => null,
                    'content'     => $sampleComments[array_rand($sampleComments)],
                    'likes_count' => 0,
                    'is_edited'   => false,
                    'is_pinned'   => ($i === 0 && rand(0, 3) === 0),
                    'is_hidden'   => false,
                ]);

                $commentsCreated++;

                // 0–2 replies per comment from a different audience user
                $replyCount = rand(0, 2);
                for ($j = 0; $j < $replyCount; $j++) {
                    // Pick a replier different from the commenter
                    $replierId = collect($this->audience)
                        ->filter(fn($id) => $id !== $commenterId)
                        ->shuffle()
                        ->first();

                    Comment::create([
                        'user_id'     => $replierId,
                        'annonce_id'  => $annonce->id,
                        'parent_id'   => $comment->id,
                        'content'     => $sampleReplies[array_rand($sampleReplies)],
                        'likes_count' => 0,
                        'is_edited'   => false,
                        'is_pinned'   => false,
                        'is_hidden'   => false,
                    ]);

                    $repliesCreated++;
                }
            }

            // Sync comments_count (top-level only)
            $annonce->update([
                'comments_count' => Comment::where('annonce_id', $annonce->id)
                    ->whereNull('parent_id')
                    ->count(),
            ]);
        }

        $this->command->info("Seeded {$commentsCreated} comments and {$repliesCreated} replies.");

        // ── CommentLikes (audience users like comments) ───────────────────────
        $this->command->info('Seeding comment likes...');

        $commentLikesInserted = 0;
        $allComments = Comment::whereNull('parent_id')->get();

        foreach ($allComments as $comment) {
            // 0–3 audience members like each comment (not the author themselves)
            $likerIds = collect($this->audience)
                ->filter(fn($id) => $id !== $comment->user_id)
                ->shuffle()
                ->take(rand(0, 3));

            foreach ($likerIds as $userId) {
                $alreadyLiked = DB::table('comment_likes')
                    ->where('comment_id', $comment->id)
                    ->where('user_id', $userId)
                    ->exists();

                if (!$alreadyLiked) {
                    DB::table('comment_likes')->insert([
                        'comment_id' => $comment->id,
                        'user_id'    => $userId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $commentLikesInserted++;
                }
            }

            // Sync likes_count on comment
            $comment->update([
                'likes_count' => DB::table('comment_likes')
                    ->where('comment_id', $comment->id)
                    ->count(),
            ]);
        }

        $this->command->info("Seeded {$commentLikesInserted} comment likes.");
        $this->command->info('✅ AnnonceSeeder completed successfully.');
    }
}