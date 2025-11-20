<?php

namespace FluentCommunity\App\Hooks\CLI;

use FluentCommunity\App\Models\Comment;
use FluentCommunity\App\Models\Feed;
use FluentCommunity\App\Models\Reaction;
use FluentCommunity\App\Models\Space;
use FluentCommunity\App\Models\SpaceGroup;
use FluentCommunity\App\Models\User;
use FluentCommunity\App\Models\XProfile;
use FluentCommunity\App\Services\FeedsHelper;
use FluentCommunity\Framework\Support\Arr;
use FluentCommunity\Framework\Support\Str;

class DymmyCommands
{
    /*
     * Seed the database with dummy data
     * usage: wp fluent_community_dummy seed --count=5000
     */
    public function seed($args, $assoc_args)
    {
        $baseCount = Arr::get($assoc_args, 'count', 10000);
        $userCount = $baseCount;
        $postCount = $userCount * 3;
        $commentCount = $postCount * 5;
        $postReactionCount = $postCount * 5;
        $commentReactionCount = $commentCount * 3;

        $this->create_users($args, ['count' => $userCount]);
        $this->create_spaces($args, ['count' => 5]);
        $this->assign_users_to_spaces($args, []);
        $this->create_posts($args, ['count' => $postCount, 'with_space' => 'yes']);
        $this->create_comments($args, ['count' => $commentCount]);
        $this->add_post_reactions($args, ['count' => $postReactionCount]);
        $this->add_comment_reactions($args, ['count' => $commentReactionCount]);

        (new Commands)->recalculate_user_points();
    }

    /*
     * Create 5 Dummy Spaces
     * usage: wp fluent_community_dummy create_spaces --count=5
     */
    public function create_spaces($args, $assoc_args = [])
    {
        $count = Arr::get($assoc_args, 'count', 5);
        $count += 1;
        $groupIds = SpaceGroup::query()->get()->pluck('id')->toArray();

        if (!$groupIds) {
            $group = SpaceGroup::create([
                'title'       => 'Get Started',
                'description' => 'Default Group Description'
            ]);
            $groupIds[] = $group->id;

            $group = SpaceGroup::create([
                'title'       => 'Product Team',
                'description' => 'Product team Group Description'
            ]);

            $groupIds[] = $group->id;
        }

        for ($i = 1; $i < $count; $i++) {
            $community = Space::create([
                'serial'      => $i,
                'parent_id'   => Arr::random($groupIds),
                'title'       => 'Random Space ' . $i,
                'description' => $this->getRandomStatus(10, 20),
                'privacy'     => 'private',
                'created_by'  => 1,
                'settings'
            ]);

            $community->members()->attach(1, [
                'role' => 'admin'
            ]);
        }

        \WP_CLI::line("Spaces created: $count");
    }

    /*
     * Create 10000 Dummy Users
     * usage: wp fluent_community_dummy create_users --count=5000
     */
    public function create_users($args, $assoc_args)
    {
        $count = Arr::get($assoc_args, 'count', 10000);

        $progress = \WP_CLI\Utils\make_progress_bar( 'Creating Users', $count, $interval = 100 );

        // let's create the wp users with subscriber role
        for ($i = 0; $i < $count; $i++) {

            $progress->tick();

            $names = $this->getRandomName();
            $firstName = $names['first_name'];
            $lastName = $names['last_name'];
            $userId = wp_insert_user([
                'user_login'   => 'user_' . $i. '_' . time(),
                'user_pass'    => wp_generate_password(),
                'user_email'   => 'user_' . $i. time() . '@example.com',
                'display_name' => $firstName . ' ' . $lastName,
                'first_name'   => $firstName,
                'last_name'    => $lastName,
                'role'         => 'subscriber'
            ]);
            $user = User::find($userId);
            $user->syncXProfile();
        }

        $progress->finish();

        \WP_CLI::line("Users created: $count");
    }

    /*
     * Assign All users to all spaces or selected spaces
     * usage: wp fluent_community_dummy assign_users_to_spaces --space_ids=1,2,3
     */
    public function assign_users_to_spaces($args, $assoc_args = [])
    {

        $providedSpaceIds = Arr::get($assoc_args, 'space_ids', '');

        if ($providedSpaceIds) {
            $spaceIds = explode(',', $providedSpaceIds);
            $spaces = Space::whereIn('id', $spaceIds)->get();
        } else {
            $spaces = Space::all();
        }

        if($spaces->isEmpty()) {
            \WP_CLI::line("No spaces found");
            return;
        }

        $profiles = User::all();

        $progress = \WP_CLI\Utils\make_progress_bar( 'Assigning Users to Spaces', $profiles->count() * $spaces->count(), $interval = 100 );

        foreach ($spaces as $space) {
            \WP_CLI::line("Assigning users to space: " . $space->title);
            foreach ($profiles as $index => $profile) {
                $progress->tick();
                if ($space->getMembership($profile->ID)) {
                    continue;
                }
                $space->members()->attach($profile->ID, [
                    'role'   => 'member',
                    'status' => 'active'
                ]);
            }
        }

        $progress->finish();
    }


    /*
     * Create 10000 Dummy Posts
     * usage: wp fluent_community_dummy create_posts --count=10000
     */
    public function create_posts($args, $assoc_args)
    {

        $totalUsersCount = XProfile::query()->count();

        $withSpace = Arr::get($assoc_args, 'with_space', 'yes') == 'yes';
        $count = Arr::get($assoc_args, 'count', 1000);

        if ($withSpace) {
            $spaces = Space::all();
            $spaceIds = $spaces->pluck('id')->toArray();
        }

        $progress = \WP_CLI\Utils\make_progress_bar( 'Generating Posts', $count, $interval = 100 );

        for ($i = 0; $i < $count; $i++) {

            $progress->tick();

            // get a random user
            $randomUserId = wp_rand(1, $totalUsersCount);

            $message = $this->getRandomStatus();

            $randomDate = current_time('mysql');

            $processedData = [
                'message'          => $message,
                'message_rendered' => wp_kses_post(FeedsHelper::mdToHtml($message)),
                'type'             => 'text',
                'content_type'     => 'text',
                'privacy'          => 'public',
                'status'           => 'published',
                'space_id'         => $withSpace ? Arr::random($spaceIds) : NULL,
                'created_at'       => $randomDate,
                'updated_at'       => $randomDate,
                'user_id'          => $randomUserId
            ];

            $feed = new Feed();
            $feed->fill($processedData);
            $feed->save();
        }

        $progress->finish();

        \WP_CLI::line("Posts created: $count");
    }
    
    /*
     * Create 10000 Dummy Comments
     * usage: wp fluent_community_dummy create_comments --count=10000
     */
    public function create_comments($args, $assoc_args)
    {
        $totalUsersCount = XProfile::query()->count();
        $totalPostCount = Feed::query()->count();
        $count = Arr::get($assoc_args, 'count', 10000);

        $createdCount = 0;

        $progress = \WP_CLI\Utils\make_progress_bar( 'Generating Comments', $count, $interval = 100 );

        for ($i = 0; $i < $count; $i++) {

            $progress->tick();

            // get a random user
            $randomUserId = wp_rand(1, $totalUsersCount);
            $postId = wp_rand(1, $totalPostCount);

            $feed = Feed::find($postId);
            if (!$feed) {
                continue;
            }
            $createdCount++;

            $message = $this->getRandomStatus(20, 80);
            $commentData = [
                'user_id'          => $randomUserId,
                'post_id'          => $feed->id,
                'message'          => $message,
                'message_rendered' => wp_kses_post(FeedsHelper::mdToHtml($message)),
                'commentable_type' => 'FluentCommunity\App\Models\Feed',
                'type'             => 'comment',
                'content_type'     => 'text',
                'status'           => 'published',
                'created_at'       => current_time('mysql'),
                'updated_at'       => current_time('mysql'),
            ];

            $comment = new Comment();
            $comment->fill($commentData);
            $comment->save();

            $feed->comments_count = $feed->comments_count + 1;
            $feed->save();
        }

        $progress->finish();

        \WP_CLI::line("Comments created: $createdCount");
    }

    /*
     * Create 10000 Dummy Reactions for Posts
     * usage: wp fluent_community_dummy add_post_reactions --count=10000
     */
    public function add_post_reactions($args, $assoc_args)
    {
        $totalUsersCount = XProfile::query()->count();
        $totalPostCount = Feed::query()->count();
        $count = Arr::get($assoc_args, 'count', 1000);

        // Create a progress bar for CLI
        $progress = \WP_CLI\Utils\make_progress_bar( 'Generating Post Reactions', $count, $interval = 100 );

        $createdCount = 0;
        for ($i = 0; $i < $count; $i++) {
            $progress->tick();
            $userId = wp_rand(1, $totalUsersCount);
            $postId = wp_rand(1, $totalPostCount);
            $post = Feed::find($postId);
            if (!$post) {
                continue;
            }

            $reactionData = [
                'user_id'     => $userId,
                'object_id'   => $post->id,
                'object_type' => 'feed',
                'type'        => 'like',
                'created_at'  => current_time('mysql'),
                'updated_at'  => current_time('mysql'),
            ];

            if ($post->reactions()->where('user_id', $userId)->count() == 0) {
                $reaction = Reaction::create($reactionData);
            } else {
                continue;
            }

            $post->reactions_count = $post->reactions_count + 1;
            $post->save();

            $createdCount++;
        }

        $progress->finish();

        \WP_CLI::line("Reaction created: $createdCount");
    }

    /*
     * Create 10000 Dummy Reactions for Comments
     * usage: wp fluent_community_dummy add_comment_reactions --count=10000
     */
    public function add_comment_reactions($args, $assoc_args)
    {
        $totalUsersCount = XProfile::query()->count();
        $totalComments = Comment::query()->count();
        $count = Arr::get($assoc_args, 'count', 10000);

        $createdCount = 0;

        $progress = \WP_CLI\Utils\make_progress_bar( 'Generating Comment Reactions', $count, $interval = 100 );

        for ($i = 0; $i < $count; $i++) {

            $progress->tick();

            $userId = wp_rand(1, $totalUsersCount);
            $commentId = wp_rand(1, $totalComments);
            $comment = Comment::find($commentId);
            if (!$comment) {
                continue;
            }

            $reactionData = [
                'user_id'     => $userId,
                'parent_id'   => $comment->post_id,
                'object_id'   => $comment->id,
                'object_type' => 'comment',
                'type'        => 'like',
                'created_at'  => gmdate('Y-m-d H:i:s', wp_rand(strtotime('-1 years'), current_time('timestamp'))),
                'updated_at'  => gmdate('Y-m-d H:i:s', wp_rand(strtotime('-1 years'), current_time('timestamp'))),
            ];

            if ($comment->reactions()->where('user_id', $userId)->count() == 0) {
                $reaction = Reaction::create($reactionData);
            } else {
                continue;
            }

            $comment->reactions_count = $comment->reactions_count + 1;
            $comment->save();

            $createdCount++;
        }

        $progress->finish();

        \WP_CLI::line("Comment Reaction created: $createdCount");

    }

    protected function getWords($count)
    {
        $words = [];
        for ($i = 0; $i < $count; $i++) {

            if ($count % 10 == 0) {
                // add a new line
                $words[] = PHP_EOL;
            }

            $words[] = Str::random(wp_rand(4, 6));
        }
        return implode(' ', $words);
    }

    protected function getRandomStatus($minWords = 80, $maxWords = 300)
    {
        // Array of sample sentences with meaningful content
        $sentences = [
            "**Technology** is advancing at an unprecedented pace, opening new possibilities for users and developers alike.",
            "Education is the cornerstone of a thriving society, and continuous learning is essential for **personal growth**.",
            "Healthcare innovations continue to improve outcomes and accessibility for patients *worldwide*.",
            "Environmental **sustainability** is more crucial now than ever, as climate change impacts global communities.",
            "Entrepreneurship drives economic growth by fostering **innovation** and creating job opportunities.",
            "Innovations like *artificial intelligence* and **blockchain** are reshaping industries.",
            "**Cybersecurity** is essential to protect data privacy and integrity in an increasingly digital world.",
            "The future of work is being shaped by **remote technologies** that connect global teams.",
            "Blockchain technology offers new ways to secure transactions and enhance *transparency*.",
            "Nutrition and wellness have become focal points for individuals seeking a *healthier lifestyle*.",
            "Cultural **diversity** enriches societies by bringing a variety of perspectives and experiences.",
            "Sports and physical activities play a crucial role in maintaining **mental and physical health**.",
            "Music and arts provide a **universal language** that bridges gaps between different cultures.",
            "Social media has transformed how we communicate and share information, but it also presents **challenges**.",
            "Mental health awareness is gaining importance, emphasizing the need for accessible **support systems**.",
            "Urban planning and development are key to creating sustainable and **livable cities** for the future.",
            "The role of **leadership** in business cannot be understated as it drives strategic direction and innovation.",
            "Conservation efforts are vital for protecting **biodiversity** and natural habitats.",
            "Technological **literacy** is becoming a fundamental skill in the digital age.",
            "Public transportation systems are evolving to provide more efficient and *eco-friendly* options.",
            "Renewable energy sources are crucial for reducing **carbon footprints** and combating climate change.",
            "The impact of global tourism on local economies and environments is a growing field of study.",
            "Personal finance management is key to achieving long-term **financial stability** and security.",
            "The publishing industry is adapting to the digital era by embracing **ebooks** and online platforms.",
            "Volunteering not only helps communities but also enriches the lives of those who **participate**.",
            "The importance of work-life balance is being recognized as essential for **well-being**.",
            "Agricultural technology is revolutionizing farming practices, making them more **sustainable** and efficient.",
            "The film industry continues to explore new **storytelling techniques** through advances in technology.",
            "Water conservation is critical in regions facing scarcity and is a global **priority**.",
            "Language learning fosters communication and understanding among **diverse populations**.",
            "Veterinary care advances are improving the lives of pets and animals in agricultural settings.",
            "Children's education is adapting to include more **digital tools** and interactive learning methods.",
            "Corporate social responsibility is becoming a standard practice for **ethical business operations**.",
            "The exploration of space continues to excite and inspire innovations in technology and **science**.",
            "Historical preservation is important for maintaining **cultural heritage** and educating future generations.",
            "The role of media in shaping public opinion is significant, requiring **responsible reporting**.",
            "Nutraceuticals are gaining popularity as consumers look for ways to improve health through **diet**.",
            "Fashion and design reflect societal trends and can influence **cultural shifts**.",
            "Telemedicine is making healthcare more accessible, especially in remote or underserved areas.",
            "Data analysis skills are increasingly valuable in a world driven by **metrics** and benchmarks.",
            "Marine conservation efforts are essential for protecting **ocean ecosystems** and species.",
            "E-commerce has transformed retail, offering convenience and a broader range of products.",
            "Public speaking and communication skills are invaluable in professional and personal settings.",
            "The integration of arts into education enhances creativity and **problem-solving** abilities.",
            "Sustainable tourism practices are essential for preserving attractions while benefiting local communities.",
            "The development of drones is impacting sectors from delivery services to **aerial photography**.",
            "Personal development is a lifelong process that involves self-awareness and **goal setting**.",
            "Community-driven initiatives can lead to substantial local changes and **empowerment**.",
            "The study of genetics is revolutionizing medicine with **personalized treatment plans**.",
            "Professional networking is a key component of career development and success.",
            "User experience design is crucial for making technology accessible and enjoyable.",
            "The preservation of wildlife through sanctuaries and reserves is critical for ecological balance.",
            "Museums play a crucial role in educating the public and preserving art and history.",
            "Biotechnology is at the forefront of developing treatments and solutions for complex diseases.",
            "Urban agriculture is growing as a solution to provide cities with fresh, local produce.",
            "Ethical hacking helps strengthen systems against malicious attacks by identifying vulnerabilities.",
            "Digital marketing strategies are crucial for businesses to reach and engage their target audience.",
            "The aging population presents unique challenges and opportunities for healthcare and society.",
            "Adventure sports are gaining popularity as people seek more thrilling and challenging experiences.",
            "Financial technology is simplifying transactions and making banking more accessible to the underserved.",
            "The debate on digital privacy continues as technology becomes more integrated into our lives.",
            "Photography not only captures moments but also communicates stories and emotions.",
            "The importance of local governance in addressing community-specific issues is increasingly recognized.",
            "Robotics in manufacturing boosts efficiency and safety, transforming production processes.",
            "Dietary trends are shifting towards plant-based options for health and environmental reasons.",
            "The significance of mentorship in career advancement is well acknowledged.",
            "The impact of climate change on weather patterns is becoming more apparent and severe.",
            "Carpooling and ride-sharing contribute to reducing traffic congestion and carbon emissions.",
            "The role of antioxidants in preventing chronic diseases is a key area of research.",
            "Craftsmanship in traditional arts is being preserved through modern techniques and education.",
            "The importance of regular exercise cannot be understated for maintaining health.",
            "Dramatic arts provide a platform for expression and understanding social issues.",
            "Literacy initiatives are crucial for empowering individuals and communities.",
            "Wearable technology is enhancing fitness monitoring and personal health management.",
            "The development of smart cities promises more efficient and sustainable urban living.",
            "Non-profit organizations play a vital role in addressing societal and environmental challenges.",
            "Personal branding is becoming more important in the digital age for professionals across fields.",
            "The study of foreign cultures enriches personal experiences and global understanding.",
            "Building effective teams is essential for success in any collaborative endeavor.",
            "The growth of podcasts as a medium for information and entertainment continues to rise.",
            "Artificial reefs are used to promote marine life and restore damaged ecosystems.",
            "Investment in public parks and recreational facilities improves community health and well-being.",
            "Understanding different leadership styles can help in managing diverse teams more effectively.",
            "The rise of micro-mobility devices like scooters impacts urban transportation dynamics.",
            "Privacy regulations are evolving to keep pace with technological advancements.",
            "Crowdfunding platforms have democratized funding for startups and creative projects.",
            "Sculpture as an art form involves both traditional techniques and modern mediums.",
            "Building resilience to natural disasters is crucial for vulnerable regions around the world.",
            "The growth of the gig economy has reshaped the concept of traditional employment.",
            "Augmented reality is creating new experiences in gaming, education, and shopping.",
            "The preservation of languages and dialects is important for maintaining cultural diversity.",
            "Virtual reality offers immersive experiences that are revolutionizing entertainment and education.",
            "The importance of saving and investment for financial independence cannot be understated.",
            "Holistic approaches to health are becoming more popular, integrating body, mind, and spirit.",
            "Green architecture is shaping the future of building by focusing on sustainability and efficiency.",
            "Effective waste management strategies are crucial for reducing environmental impact.",
            "The expansion of online education provides access to learning opportunities regardless of location.",
            "Understanding market trends is essential for businesses to adapt and thrive.",
            "The role of quantum computing in future technological developments is highly anticipated."
        ];

        // Shuffle sentences for variety
        shuffle($sentences);

        // Generate the paragraph
        $wordCount = 0;
        $paragraph = "";
        while ($wordCount < $minWords) {
            $sentence = array_shift($sentences);

            $paragraph .= $sentence . PHP_EOL . '<br />';
            $wordCount += str_word_count($sentence);

            // Re-shuffle and refill sentences if needed
            if (empty($sentences)) {
                shuffle($sentences);
            }
        }

        // Trim the paragraph if it exceeds the maximum word limit
        if ($wordCount > $maxWords) {
            $words = preg_split('/\s+/', $paragraph);
            $paragraph = implode(' ', array_slice($words, 0, $maxWords));
        }

        return $paragraph;
    }

    protected function getRandomName()
    {
        $firstNames = [
            "Liam", "Olivia", "Noah", "Emma", "Oliver", "Ava", "Elijah", "Sophia", "William", "Isabella",
            "James", "Charlotte", "Benjamin", "Amelia", "Lucas", "Mia", "Henry", "Harper", "Alexander", "Evelyn",
            "Ethan", "Abigail", "Jacob", "Emily", "Michael", "Ella", "Daniel", "Elizabeth", "Logan", "Camila",
            "Matthew", "Luna", "Aiden", "Sofia", "Joseph", "Avery", "Sebastian", "Mila", "Jackson", "Scarlett",
            "David", "Eleanor", "Samuel", "Madison", "Carter", "Layla", "Wyatt", "Penelope", "John", "Aria",
            "Owen", "Chloe", "Dylan", "Grace", "Luke", "Ellie", "Gabriel", "Nora", "Anthony", "Hazel",
            "Isaac", "Zoey", "Grayson", "Riley", "Jack", "Victoria", "Julian", "Lily", "Levi", "Aurora",
            "Christopher", "Violet", "Joshua", "Nova", "Andrew", "Hannah", "Lincoln", "Emilia", "Mateo", "Zoe",
            "Ryan", "Stella", "Jaxon", "Everly", "Nathan", "Isla", "Aaron", "Leah", "Isaiah", "Lillian",
            "Charles", "Addison", "Caleb", "Willow", "Josiah", "Lucy", "Christian", "Paisley", "Hunter", "Natalie",
            "Eli", "Naomi", "Jonathan", "Eliana", "Connor", "Brooklyn", "Landon", "Elena", "Adrian", "Aubrey",
            "Asher", "Claire", "Cameron", "Ivy", "Leo", "Kinsley", "Theodore", "Audrey", "Jeremiah", "Maya",
            "Hudson", "Genesis", "Robert", "Skylar", "Easton", "Bella", "Nolan", "Aaliyah", "Nicholas", "Madelyn",
            "Ezra", "Savannah", "Colton", "Anna", "Angel", "Delilah", "Brayden", "Serenity", "Jordan", "Caroline",
            "Austin", "Kennedy", "Adriel", "Valentina", "Jace", "Ruby", "Cooper", "Sophie", "Xavier", "Alice",
            "Carson", "Gabriella", "Dominic", "Sadie", "Josiah", "Ariana", "Micah", "Allison", "Christopher", "Hailey",
            "Kyrie", "Autumn", "Luca", "Nevaeh", "Jameson", "Natalia", "Camden", "Quinn", "Kai", "Josephine",
            "Bryson", "Sarah", "Weston", "Cora", "Jason", "Emery", "Harrison", "Samantha", "Theo", "Piper",
            "Silas", "Leilani", "George", "Paige", "Kayden", "Mackenzie", "Reid", "Lydia", "Wesley", "Jade",
            "Braxton", "Peyton", "Declan", "Brianna", "Brooks", "Maria", "Jude", "Anastasia", "Antonio", "Isabelle",
            "Cole", "Taylor", "Axel", "Rylee", "Miles", "London", "Sawyer", "Jasmine", "Ryder", "Gianna",
            "Gavin", "Alaina", "Leonardo", "Liliana", "Ayden", "Sofia", "Bennett", "Kaitlyn", "Sean", "Harmony",
            "Beckett", "Daisy", "Ryker", "Alexa", "Liam", "Kayla", "Thomas", "Adalynn", "Oscar", "Vivian",
        ];
        $lastNames = [
            "Smith", "Johnson", "Williams", "Brown", "Jones", "Miller", "Davis", "Garcia", "Rodriguez", "Wilson",
            "Martinez", "Anderson", "Taylor", "Thomas", "Hernandez", "Moore", "Martin", "Jackson", "Thompson", "White",
            "Lopez", "Lee", "Gonzalez", "Harris", "Clark", "Lewis", "Robinson", "Walker", "Perez", "Hall",
            "Young", "Allen", "Sanchez", "Wright", "King", "Scott", "Green", "Baker", "Adams", "Nelson",
            "Hill", "Ramirez", "Campbell", "Mitchell", "Roberts", "Carter", "Phillips", "Evans", "Turner", "Torres",
            "Parker", "Collins", "Edwards", "Stewart", "Flores", "Morris", "Nguyen", "Murphy", "Rivera", "Cook",
            "Rogers", "Morgan", "Peterson", "Cooper", "Reed", "Bailey", "Bell", "Gomez", "Kelly", "Howard",
            "Ward", "Cox", "Diaz", "Richardson", "Wood", "Watson", "Brooks", "Bennett", "Gray", "James",
            "Reyes", "Cruz", "Hughes", "Price", "Myers", "Long", "Foster", "Sanders", "Ross", "Morales",
            "Powell", "Sullivan", "Russell", "Ortiz", "Jenkins", "Gutierrez", "Perry", "Butler", "Barnes", "Fisher",
            "Henderson", "Coleman", "Simmons", "Patterson", "Jordan", "Reynolds", "Hamilton", "Graham", "Kim", "Gonzales",
            "Alexander", "Ramos", "Wallace", "Griffin", "West", "Cole", "Hayes", "Chavez", "Gibson", "Bryant",
            "Ellis", "Stevens", "Murray", "Ford", "Marshall", "Owens", "Mcdonald", "Harrison", "Ruiz", "Kennedy",
            "Wells", "Alvarez", "Woods", "Mendoza", "Castillo", "Olson", "Webb", "Washington", "Tucker", "Freeman",
            "Burns", "Henry", "Vasquez", "Snyder", "Simpson", "Crawford", "Jimenez", "Porter", "Mason", "Shaw",
            "Gordon", "Wagner", "Hunter", "Romero", "Hicks", "Dixon", "Hunt", "Palmer", "Robertson", "Black",
            "Holmes", "Stone", "Meyer", "Boyd", "Mills", "Warren", "Fox", "Rose", "Rice", "Moreno",
            "Schmidt", "Patel", "Ferguson", "Nichols", "Herrera", "Medina", "Ryan", "Fernandez", "Weaver", "Daniels",
            "Stephens", "Gardner", "Payne", "Kelley", "Dunn", "Pierce", "Arnold", "Tran", "Spencer", "Peters",
            "Hawkins", "Grant", "Hansen", "Castro", "Hoffman", "Hart", "Elliott", "Cunningham", "Knight", "Bradley",
            "Carroll", "Hudson", "Duncan", "Armstrong", "Berry", "Andrews", "Johnston", "Ray", "Lane", "Riley",
            "Carpenter", "Perkins", "Aguilar", "Silva", "Richards", "Willis", "Matthews", "Chapman", "Lawrence", "Garza",
            "Vargas", "Watkins", "Wheeler", "Larson", "Carlson", "Harper", "George", "Greene", "Burke", "Guzman",
            "Morrison", "Munoz", "Jacobs", "Obrien", "Lawson", "Franklin", "Lynch", "Bishop", "Carr", "Salazar",
            "Austin", "Mendez", "Gilbert", "Jensen", "Williamson", "Montgomery", "Harvey", "Oliver", "Howell", "Dean",
            "Hanson", "Weber", "Garrett", "Sims", "Burton", "Fuller", "Soto", "Mccarthy", "Rodriguez", "Chang",
            "Mullins", "Benson", "Sharp", "Bowen", "Daniel", "Barber", "Cummings", "Hines", "Baldwin", "Griffith",
            "Valdez", "Hubbard", "Salinas", "Reeves", "Warner", "Stevenson", "Burgess", "Santos", "Tate", "Cross",
            "Garner", "Mann", "Mack", "Moss", "Thornton", "Dennis", "Mcgee", "Farmer", "Delgado", "Aguirre",
            "Pacheco", "Blair", "Hogan", "Michael", "Donovan", "Mcintosh", "Walls", "Boone", "Charles", "Gill",
            "Godfrey", "Lang", "Combs", "Kramer", "Heath", "Hancock", "Gallagher", "Gaines", "Shaffer", "Short",
            "Wiggins", "Mathews", "Mcclain", "Fischer", "Wall", "Small", "Melton", "Hensley", "Bond", "Dyer",
            "Cameron", "Grimes", "Contreras", "Christian", "Wyatt", "Baxter", "Snow", "Mosley", "Shepherd", "Larsen",
            "Hoover", "Beasley", "Glenn", "Petersen", "Whitehead", "Meyers", "Keith", "Garrison", "Vincent", "Shields",
            "Horn", "Savage", "Olsen", "Schroeder", "Hartman", "Woodard", "Mueller", "Kemp", "Deleon", "Booth",
            "Patel", "Calhoun", "Wiley", "Eaton", "Cline", "Navarro", "Harrell", "Lester", "Humphrey", "Parrish",
            "Duran", "Hutchinson", "Hess", "Dorsey", "Bullock", "Robles", "Beard", "Dalton", "Avila", "Vance",
            "Rich", "Blackwell", "York", "Johns", "Blankenship", "Trevino", "Salinas", "Campos", "Pruitt", "Moses",
            "Callahan", "Golden", "Montoya", "Hardin", "Guerra", "Mcdowell", "Carey", "Stafford", "Gallegos", "Henson",
            "Wilkinson", "Booker", "Merritt", "Miranda", "Atkinson", "Orr", "Decker", "Hobbs", "Preston", "Tanner",
            "Knox", "Pacheco", "Stephenson", "Glass", "Rojas", "Serrano", "Marks", "Hickman", "English", "Sweeney",
            "Strong", "Prince", "Mcclure", "Conway", "Walter", "Roth", "Maynard", "Farrell", "Lowery", "Hurst",
            "Nixon", "Weiss", "Trujillo", "Ellison", "Sloan", "Juarez", "Winters", "Mclean", "Randolph", "Leon",
            "Boyer", "Villarreal", "Mccall", "Gentry", "Carrillo", "Kent", "Ayers", "Lara", "Shannon", "Sexton",
            "Pace", "Hull", "Leblanc", "Browning", "Velasquez", "Leach", "Chang", "House", "Sellers", "Herring",
            "Noble", "Foley", "Bartlett", "Mercado", "Landry", "Durham", "Walls", "Barr", "Mckee", "Bauer",
            "Rivers", "Everett", "Bradshaw", "Pugh", "Velez", "Rush", "Estes", "Dodson", "Morse", "Sheppard",
            "Weeks", "Camacho", "Bean", "Barron", "Livingston", "Middleton", "Spears", "Branch", "Blevins", "Chen",
            "Kerr", "Mcconnell", "Hatfield", "Harding", "Ashley", "Solis", "Herman", "Frost", "Giles", "Blackburn",
            "William", "Pennington", "Woodward", "Finley", "Mcintosh", "Koch", "Best", "Solomon", "Mccullough", "Dudley",
            "Nolan", "Blanchard", "Rivas", "Brennan", "Mejia", "Kane", "Benton", "Joyce", "Buckley", "Haley",
            "Valentine", "Maddox", "Russo", "Mcknight", "Buck", "Moon", "Mcmillan", "Crosby", "Berg", "Dotson",
            "Mays", "Roach", "Church", "Chan", "Richmond", "Meadows", "Faulkner", "Oneill", "Knapp", "Kline",
            "Barry", "Ochoa", "Jacobson", "Gay", "Avery", "Hendricks", "Horne", "Shepard", "Hebert", "Cherry",
            "Cardenas", "Mcintyre", "Whitney", "Waller", "Holman", "Donaldson", "Cantu", "Terrell", "Morin", "Gillespie",
            "Fuentes", "Tillman", "Sanford", "Bentley", "Peck", "Key", "Salas", "Rollins", "Gamble", "Dickson",
            "Battle", "Santana", "Cabrera", "Cervantes", "Howe", "Hinton", "Hurley", "Spence", "Zamora", "Yang",
            "Mcneil", "Suarez", "Case", "Petty", "Gould", "Mcfarland", "Sampson", "Carver", "Bray", "Rosario",
            "Macdonald", "Stout", "Hester", "Melendez", "Dillon", "Farley", "Hopper", "Galloway", "Potts", "Bernard",
            "Joyner", "Stein", "Aguirre", "Osborn", "Mercer", "Bender", "Franco", "Rowland", "Sykes", "Benjamin",
            "Travis", "Pickett", "Crane", "Sears", "Mayo", "Dunlap", "Hayden", "Wilder", "Mckay", "Coffey",
            "Mccarty", "Ewing", "Cooley", "Vaughan", "Bonner", "Cotton", "Holder", "Stark", "Ferrell", "Cantrell",
            "Fulton", "Lynn", "Lott", "Calderon", "Rosa", "Pollard", "Hooper", "Burch", "Mullen", "Fry",
            "Riddle", "Levy", "David", "Duke", "Odonnell", "Guy", "Michael", "Britt", "Frederick", "Daugherty",
            "Berger", "Dillard", "Alston", "Jarvis", "Frye", "Riggs", "Chaney", "Odom", "Duffy", "Fitzpatrick",
            "Valenzuela", "Merrill", "Mayer", "Alford", "Mcpherson", "Acevedo", "Donovan", "Barrera", "Albert", "Cote",
            "Reilly", "Compton", "Raymond", "Mooney", "Mcgowan", "Craft", "Cleveland", "Clemons", "Wynn", "Nielsen",
            "Baird", "Stanton", "Snider", "Rosales", "Bright", "Witt", "Stuart", "Hays", "Holden", "Rutledge",
            "Kinney", "Clements", "Castaneda", "Slater", "Hahn", "Emerson", "Conrad", "Burks", "Delaney", "Pate",
            "Lancaster", "Sweet", "Justice", "Tyson", "Sharpe", "Whitfield", "Talley", "Macias", "Irwin", "Burris",
            "Ratliff", "Mccray", "Madden", "Kaufman", "Beach", "Goff", "Cash", "Bolton", "Mcfadden", "Levine",
            "Good", "Byers", "Kirkland", "Kidd", "Workman", "Carney", "Dale", "Mcleod", "Holcomb", "England",
            "Finch", "Head", "Burt", "Hendrix", "Sosa", "Haney", "Franks", "Sargent", "Nieves", "Downs",
            "Rasmussen", "Bird", "Hewitt", "Lindsay", "Le", "Foreman", "Valencia", "Oneil", "Delacruz", "Vinson",
            "Dejesus", "Hyde", "Forbes", "Gilliam", "Guthrie", "Wooten", "Huber", "Barlow", "Boyle", "McMahon",
            "Buckner", "Rocha", "Puckett", "Langley", "Knowles", "Cooke", "Velazquez", "Whitley", "Noel", "Vang"
        ];

        return [
            'first_name' => Arr::random($firstNames),
            'last_name'  => Arr::random($lastNames)
        ];
    }
}
