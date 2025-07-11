<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\User;

class HelpContentSeeder extends Seeder
{
    public function run()
    {
        // Get or create an admin user for content creation
        $admin = $this->getOrCreateAdmin();

        // Seed help categories
        $this->seedHelpCategories();
        
        // Seed FAQs
        $this->seedFAQs($admin);
        
        // Seed resource categories (if you have them)
        $this->seedResourceCategories();
        
        // Seed resources (if you have them)
        $this->seedResources($admin);

        $this->command->info('✨ Help content seeding completed successfully!');
    }

    /**
     * Get or create an admin user
     */
    private function getOrCreateAdmin()
    {
        $admin = User::where('role', 'admin')->first();
        
        if (!$admin) {
            // If no admin exists, try to get the first user or create one
            $admin = User::first();
            if ($admin) {
                $admin->update(['role' => 'admin']);
            } else {
                $admin = User::factory()->create([
                    'name' => 'Help System Admin',
                    'email' => 'admin@example.com',
                    'role' => 'admin',
                ]);
            }
        }

        return $admin;
    }

    /**
     * Seed help categories
     */
    private function seedHelpCategories()
    {
        if (DB::table('help_categories')->count() > 0) {
            $this->command->info('Help categories already exist, skipping...');
            return;
        }

        $categories = [
            [
                'name' => 'Getting Started',
                'slug' => 'getting-started',
                'description' => 'Basic information to help you get started with our platform',
                'icon' => 'Play',
                'color' => '#10B981',
                'sort_order' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Appointments',
                'slug' => 'appointments',
                'description' => 'Everything about booking, managing, and attending appointments',
                'icon' => 'Calendar',
                'color' => '#3B82F6',
                'sort_order' => 2,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Account & Profile',
                'slug' => 'account-profile',
                'description' => 'Managing your account settings and profile information',
                'icon' => 'User',
                'color' => '#8B5CF6',
                'sort_order' => 3,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Mental Health Support',
                'slug' => 'mental-health',
                'description' => 'Mental health resources, counseling, and support services',
                'icon' => 'Heart',
                'color' => '#EC4899',
                'sort_order' => 4,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Academic Support',
                'slug' => 'academic-support',
                'description' => 'Academic guidance, study resources, and educational support',
                'icon' => 'BookOpen',
                'color' => '#F59E0B',
                'sort_order' => 5,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Services',
                'slug' => 'services',
                'description' => 'Information about available counseling and support services',
                'icon' => 'HandHeart',
                'color' => '#06B6D4',
                'sort_order' => 6,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Crisis Support',
                'slug' => 'crisis-support',
                'description' => 'Immediate help and crisis intervention resources',
                'icon' => 'AlertTriangle',
                'color' => '#EF4444',
                'sort_order' => 7,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Privacy & Security',
                'slug' => 'privacy',
                'description' => 'Information about data privacy, security, and confidentiality',
                'icon' => 'Shield',
                'color' => '#059669',
                'sort_order' => 8,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Technical Help',
                'slug' => 'technical-help',
                'description' => 'Technical support, troubleshooting, and platform guidance',
                'icon' => 'Settings',
                'color' => '#6B7280',
                'sort_order' => 9,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('help_categories')->insert($categories);
        $this->command->info('✅ Seeded help categories');
    }

    /**
     * Seed comprehensive FAQs
     */
    private function seedFAQs($admin)
    {
        if (DB::table('faqs')->count() > 0) {
            $this->command->info('FAQs already exist, skipping...');
            return;
        }

        // Get category IDs - use actual slugs from database
        $categories = DB::table('help_categories')->pluck('id', 'slug');
        
        // Check if we have the categories we need, if not skip or create them
        $requiredSlugs = ['getting-started', 'appointments', 'account-profile', 'mental-health', 'academic-support', 'services', 'crisis-support', 'privacy', 'technical-help'];
        
        $missingSlugs = array_diff($requiredSlugs, $categories->keys()->toArray());
        
        if (!empty($missingSlugs)) {
            $this->command->info('Missing required categories: ' . implode(', ', $missingSlugs) . '. Skipping FAQ seeding.');
            return;
        }

        $faqs = [
            // Getting Started FAQs
            [
                'category_id' => $categories['getting-started'],
                'question' => 'How do I get started with the platform?',
                'answer' => 'Welcome! To get started, complete your profile setup, browse available services, and book your first appointment. You can also explore our resource library for self-help materials and guides.',
                'tags' => ['getting-started', 'onboarding', 'first-time'],
                'is_published' => true,
                'is_featured' => true,
                'sort_order' => 1,
                'helpful_count' => 45,
                'not_helpful_count' => 2,
                'view_count' => 320,
                'published_at' => now(),
            ],
            [
                'category_id' => $categories['getting-started'],
                'question' => 'What services are available to me?',
                'answer' => 'We offer individual counseling, group therapy, crisis support, academic counseling, and a comprehensive resource library. All services are free for enrolled students and can be accessed through your dashboard.',
                'tags' => ['services', 'overview', 'available'],
                'is_published' => true,
                'is_featured' => true,
                'sort_order' => 2,
                'helpful_count' => 38,
                'not_helpful_count' => 1,
                'view_count' => 285,
                'published_at' => now(),
            ],

            // Appointments FAQs
            [
                'category_id' => $categories['appointments'],
                'question' => 'How do I book an appointment with a counselor?',
                'answer' => 'You can book an appointment by navigating to the "Appointments" section in your dashboard. Click "Book Appointment", choose your preferred type (video, phone, or in-person), select an available counselor, pick a date and time that works for you, and confirm your booking. You\'ll receive a confirmation email with all the details.',
                'tags' => ['booking', 'appointment', 'counselor', 'schedule'],
                'is_published' => true,
                'is_featured' => true,
                'sort_order' => 1,
                'helpful_count' => 67,
                'not_helpful_count' => 3,
                'view_count' => 450,
                'published_at' => now(),
            ],
            [
                'category_id' => $categories['appointments'],
                'question' => 'Can I cancel or reschedule my appointment?',
                'answer' => 'Yes, you can cancel or reschedule appointments up to 24 hours before the scheduled time. Go to your "My Appointments" page, find the appointment you want to change, and click either "Reschedule" or "Cancel". Please note that late cancellations (less than 24 hours) may be subject to our cancellation policy.',
                'tags' => ['cancel', 'reschedule', 'appointment', 'policy'],
                'is_published' => true,
                'is_featured' => false,
                'sort_order' => 2,
                'helpful_count' => 34,
                'not_helpful_count' => 5,
                'view_count' => 278,
                'published_at' => now(),
            ],
            [
                'category_id' => $categories['appointments'],
                'question' => 'What should I expect during my first counseling session?',
                'answer' => 'Your first session will typically involve getting to know your counselor, discussing your concerns and goals, and developing a plan for future sessions. It\'s normal to feel nervous - your counselor will help you feel comfortable. The session usually lasts 50 minutes, and everything discussed is confidential.',
                'tags' => ['first session', 'expectations', 'counseling', 'confidential'],
                'is_published' => true,
                'is_featured' => true,
                'sort_order' => 3,
                'helpful_count' => 89,
                'not_helpful_count' => 2,
                'view_count' => 567,
                'published_at' => now(),
            ],
            [
                'category_id' => $categories['appointments'],
                'question' => 'Can I request a specific counselor?',
                'answer' => 'Yes, you can request a specific counselor when booking your appointment. However, availability may vary, and we encourage you to consider multiple options. If your preferred counselor isn\'t available, our matching system can suggest counselors with similar specializations.',
                'tags' => ['counselor', 'request', 'specific', 'matching'],
                'is_published' => true,
                'is_featured' => false,
                'sort_order' => 4,
                'helpful_count' => 28,
                'not_helpful_count' => 7,
                'view_count' => 189,
                'published_at' => now(),
            ],

            // Account & Profile FAQs
            [
                'category_id' => $categories['account-profile'],
                'question' => 'How do I update my profile information?',
                'answer' => 'To update your profile, go to Settings > Profile from your dashboard. You can edit your personal information, contact details, emergency contacts, and preferences. Remember to save your changes before leaving the page.',
                'tags' => ['profile', 'update', 'information', 'settings'],
                'is_published' => true,
                'is_featured' => false,
                'sort_order' => 1,
                'helpful_count' => 23,
                'not_helpful_count' => 2,
                'view_count' => 156,
                'published_at' => now(),
            ],
            [
                'category_id' => $categories['account-profile'],
                'question' => 'How do I change my notification preferences?',
                'answer' => 'You can manage your notification preferences in Settings > Notifications. Choose how you want to receive appointment reminders, system updates, and resource recommendations via email, SMS, or in-app notifications.',
                'tags' => ['notifications', 'preferences', 'email', 'SMS'],
                'is_published' => true,
                'is_featured' => false,
                'sort_order' => 2,
                'helpful_count' => 19,
                'not_helpful_count' => 1,
                'view_count' => 134,
                'published_at' => now(),
            ],

            // Mental Health Support FAQs
            [
                'category_id' => $categories['mental-health'],
                'question' => 'What mental health resources are available?',
                'answer' => 'We offer various mental health resources including individual counseling sessions, group therapy, crisis intervention, self-help materials, guided meditation sessions, and mental health workshops. You can access these through your dashboard or by speaking with a counselor.',
                'tags' => ['mental-health', 'counseling', 'resources', 'therapy'],
                'is_published' => true,
                'is_featured' => true,
                'sort_order' => 1,
                'helpful_count' => 95,
                'not_helpful_count' => 4,
                'view_count' => 623,
                'published_at' => now(),
            ],
            [
                'category_id' => $categories['mental-health'],
                'question' => 'How do I know if I need professional help?',
                'answer' => 'Consider seeking professional help if you\'re experiencing persistent sadness, anxiety, difficulty sleeping, changes in appetite, trouble concentrating, or thoughts of self-harm. Our counselors can help assess your needs and provide appropriate support.',
                'tags' => ['professional help', 'assessment', 'mental health', 'counseling'],
                'is_published' => true,
                'is_featured' => true,
                'sort_order' => 2,
                'helpful_count' => 78,
                'not_helpful_count' => 6,
                'view_count' => 445,
                'published_at' => now(),
            ],
            [
                'category_id' => $categories['mental-health'],
                'question' => 'Are there self-help resources I can use?',
                'answer' => 'Yes! Our resource library includes mindfulness exercises, self-assessment tools, coping strategies, guided meditations, and educational materials. These can complement professional counseling or serve as standalone support tools.',
                'tags' => ['self-help', 'resources', 'mindfulness', 'coping strategies'],
                'is_published' => true,
                'is_featured' => false,
                'sort_order' => 3,
                'helpful_count' => 56,
                'not_helpful_count' => 3,
                'view_count' => 367,
                'published_at' => now(),
            ],

            // Academic Support FAQs
            [
                'category_id' => $categories['academic-support'],
                'question' => 'How can counseling help with my academic performance?',
                'answer' => 'Academic counseling can help with study skills, time management, test anxiety, motivation issues, and stress related to academic pressure. We also offer specialized support for learning differences and academic accommodations.',
                'tags' => ['academic', 'performance', 'study skills', 'test anxiety'],
                'is_published' => true,
                'is_featured' => true,
                'sort_order' => 1,
                'helpful_count' => 72,
                'not_helpful_count' => 8,
                'view_count' => 398,
                'published_at' => now(),
            ],
            [
                'category_id' => $categories['academic-support'],
                'question' => 'What resources are available for test anxiety?',
                'answer' => 'We offer specialized counseling for test anxiety, relaxation techniques, study strategies, and mindfulness exercises. You can also access our self-help materials on exam preparation and stress management.',
                'tags' => ['test anxiety', 'exam stress', 'relaxation', 'study strategies'],
                'is_published' => true,
                'is_featured' => false,
                'sort_order' => 2,
                'helpful_count' => 43,
                'not_helpful_count' => 5,
                'view_count' => 289,
                'published_at' => now(),
            ],

            // Services FAQs
            [
                'category_id' => $categories['services'],
                'question' => 'What types of counseling services are available?',
                'answer' => 'We offer individual counseling, group therapy, crisis intervention, academic counseling, and specialized support for anxiety, depression, stress management, and relationship issues. All services are provided by licensed professionals who specialize in working with students.',
                'tags' => ['services', 'counseling types', 'therapy', 'specializations'],
                'is_published' => true,
                'is_featured' => true,
                'sort_order' => 1,
                'helpful_count' => 84,
                'not_helpful_count' => 3,
                'view_count' => 512,
                'published_at' => now(),
            ],
            [
                'category_id' => $categories['services'],
                'question' => 'Are counseling services free for students?',
                'answer' => 'Yes, all basic counseling services are provided free of charge to enrolled students. This includes individual sessions, group therapy, crisis support, and access to our resource library. Some specialized programs may have limited availability, but we strive to ensure all students have access to the support they need.',
                'tags' => ['free', 'cost', 'students', 'services'],
                'is_published' => true,
                'is_featured' => false,
                'sort_order' => 2,
                'helpful_count' => 67,
                'not_helpful_count' => 2,
                'view_count' => 423,
                'published_at' => now(),
            ],
            [
                'category_id' => $categories['services'],
                'question' => 'How many counseling sessions can I have?',
                'answer' => 'There\'s no strict limit on the number of sessions you can have. Your counselor will work with you to determine the appropriate frequency and duration of treatment based on your individual needs and goals.',
                'tags' => ['sessions', 'limit', 'frequency', 'treatment'],
                'is_published' => true,
                'is_featured' => false,
                'sort_order' => 3,
                'helpful_count' => 35,
                'not_helpful_count' => 4,
                'view_count' => 245,
                'published_at' => now(),
            ],

            // Crisis Support FAQs
            [
                'category_id' => $categories['crisis-support'],
                'question' => 'What should I do if I\'m having a mental health crisis?',
                'answer' => 'If you\'re experiencing a mental health crisis, please reach out immediately. Use our Crisis Support button for 24/7 assistance, call the National Suicide Prevention Lifeline at 988, or contact emergency services at 911 if you\'re in immediate danger. You\'re not alone, and help is always available.',
                'tags' => ['crisis', 'emergency', 'suicide prevention', '988', 'help'],
                'is_published' => true,
                'is_featured' => true,
                'sort_order' => 1,
                'helpful_count' => 156,
                'not_helpful_count' => 1,
                'view_count' => 789,
                'published_at' => now(),
            ],
            [
                'category_id' => $categories['crisis-support'],
                'question' => 'Is crisis support available 24/7?',
                'answer' => 'Yes, our crisis support services are available 24/7. You can access immediate help through our crisis hotline, online chat, or by visiting the emergency room. We also have on-call counselors for urgent situations.',
                'tags' => ['24/7', 'crisis support', 'emergency', 'hotline'],
                'is_published' => true,
                'is_featured' => true,
                'sort_order' => 2,
                'helpful_count' => 98,
                'not_helpful_count' => 2,
                'view_count' => 567,
                'published_at' => now(),
            ],

            // Privacy & Security FAQs
            [
                'category_id' => $categories['privacy'],
                'question' => 'Is my information kept confidential?',
                'answer' => 'Yes, absolutely. All your personal information, session notes, and communications are kept strictly confidential in accordance with HIPAA regulations and our privacy policy. Information is only shared with your explicit consent or in cases where there\'s immediate danger to yourself or others.',
                'tags' => ['confidential', 'privacy', 'HIPAA', 'security'],
                'is_published' => true,
                'is_featured' => true,
                'sort_order' => 1,
                'helpful_count' => 123,
                'not_helpful_count' => 5,
                'view_count' => 678,
                'published_at' => now(),
            ],
            [
                'category_id' => $categories['privacy'],
                'question' => 'Who can see my appointment history?',
                'answer' => 'Your appointment history is only visible to you and your assigned counselors. Administrative staff may have limited access for scheduling purposes only. We follow strict data protection protocols to ensure your privacy is maintained at all times.',
                'tags' => ['appointment history', 'privacy', 'access', 'data protection'],
                'is_published' => true,
                'is_featured' => false,
                'sort_order' => 2,
                'helpful_count' => 67,
                'not_helpful_count' => 3,
                'view_count' => 345,
                'published_at' => now(),
            ],
            [
                'category_id' => $categories['privacy'],
                'question' => 'How is my data protected?',
                'answer' => 'We use industry-standard encryption for all data transmission and storage. Our systems are regularly audited for security, and all staff receive training on privacy protection. We comply with HIPAA, FERPA, and other relevant privacy regulations.',
                'tags' => ['data protection', 'encryption', 'security', 'compliance'],
                'is_published' => true,
                'is_featured' => false,
                'sort_order' => 3,
                'helpful_count' => 45,
                'not_helpful_count' => 2,
                'view_count' => 234,
                'published_at' => now(),
            ],

            // Technical Help FAQs
            [
                'category_id' => $categories['technical-help'],
                'question' => 'How do I reset my password?',
                'answer' => 'To reset your password, click the "Forgot Password" link on the login page. Enter your email address and you\'ll receive a password reset link within a few minutes. Follow the instructions in the email to create a new password. If you don\'t receive the email, check your spam folder or contact support.',
                'tags' => ['password', 'reset', 'login', 'account'],
                'is_published' => true,
                'is_featured' => false,
                'sort_order' => 1,
                'helpful_count' => 89,
                'not_helpful_count' => 12,
                'view_count' => 445,
                'published_at' => now(),
            ],
            [
                'category_id' => $categories['technical-help'],
                'question' => 'Why can\'t I access video sessions?',
                'answer' => 'Video session issues are usually related to browser permissions or internet connectivity. Make sure you\'ve allowed camera and microphone access in your browser settings. We recommend using Chrome or Firefox for the best experience. If problems persist, try refreshing the page or contact our technical support team.',
                'tags' => ['video', 'technical', 'browser', 'permissions'],
                'is_published' => true,
                'is_featured' => false,
                'sort_order' => 2,
                'helpful_count' => 56,
                'not_helpful_count' => 8,
                'view_count' => 234,
                'published_at' => now(),
            ],
            [
                'category_id' => $categories['technical-help'],
                'question' => 'What browsers are supported?',
                'answer' => 'We support the latest versions of Chrome, Firefox, Safari, and Edge. For the best experience with video sessions, we recommend Chrome or Firefox. Make sure your browser is up to date and has JavaScript enabled.',
                'tags' => ['browsers', 'supported', 'compatibility', 'video'],
                'is_published' => true,
                'is_featured' => false,
                'sort_order' => 3,
                'helpful_count' => 34,
                'not_helpful_count' => 3,
                'view_count' => 178,
                'published_at' => now(),
            ],
            [
                'category_id' => $categories['technical-help'],
                'question' => 'How do I report a technical issue?',
                'answer' => 'You can report technical issues through the "Help" menu in your dashboard, send an email to our technical support team, or use the live chat feature. Please include details about your browser, device, and the specific issue you\'re experiencing.',
                'tags' => ['technical issues', 'report', 'support', 'troubleshooting'],
                'is_published' => true,
                'is_featured' => false,
                'sort_order' => 4,
                'helpful_count' => 28,
                'not_helpful_count' => 2,
                'view_count' => 145,
                'published_at' => now(),
            ],
        ];

        foreach ($faqs as $faqData) {
            $faqData['slug'] = Str::slug($faqData['question']) . '-' . time() . '-' . rand(1000, 9999);
            $faqData['created_by'] = $admin->id;
            $faqData['tags'] = json_encode($faqData['tags']);
            $faqData['created_at'] = now();
            $faqData['updated_at'] = now();

            DB::table('faqs')->insert($faqData);
        }

        $this->command->info('✅ Seeded comprehensive FAQs');
    }

    /**
     * Seed resource categories (if you have a resources system)
     */
    private function seedResourceCategories()
    {
        // Check if resource_categories table exists
        if (!DB::getSchemaBuilder()->hasTable('resource_categories')) {
            $this->command->info('Resource categories table does not exist, skipping...');
            return;
        }

        if (DB::table('resource_categories')->count() > 0) {
            $this->command->info('Resource categories already exist, skipping...');
            return;
        }

        $resourceCategories = [
            [
                'name' => 'Mental Health',
                'slug' => 'mental-health',
                'description' => 'Resources for mental health and emotional wellbeing',
                'icon' => 'Heart',
                'color' => '#EC4899',
                'sort_order' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Academic Success',
                'slug' => 'academic-success',
                'description' => 'Study skills, time management, and academic support resources',
                'icon' => 'BookOpen',
                'color' => '#F59E0B',
                'sort_order' => 2,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Social Wellness',
                'slug' => 'social-wellness',
                'description' => 'Building relationships and social skills',
                'icon' => 'Users',
                'color' => '#8B5CF6',
                'sort_order' => 3,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Physical Wellness',
                'slug' => 'physical-wellness',
                'description' => 'Physical health, exercise, and wellness resources',
                'icon' => 'Activity',
                'color' => '#10B981',
                'sort_order' => 4,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Life Skills',
                'slug' => 'life-skills',
                'description' => 'Practical life skills and personal development',
                'icon' => 'Target',
                'color' => '#06B6D4',
                'sort_order' => 5,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Crisis Resources',
                'slug' => 'crisis-resources',
                'description' => 'Emergency and crisis intervention resources',
                'icon' => 'AlertTriangle',
                'color' => '#EF4444',
                'sort_order' => 6,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('resource_categories')->insert($resourceCategories);
        $this->command->info('✅ Seeded resource categories');
    }

    /**
     * Seed sample resources (if you have a resources system)
     */
    private function seedResources($admin)
    {
        // Check if resources table exists
        if (!DB::getSchemaBuilder()->hasTable('resources')) {
            $this->command->info('Resources table does not exist, skipping...');
            return;
        }

        if (DB::table('resources')->count() > 0) {
            $this->command->info('Resources already exist, skipping...');
            return;
        }

        // Get resource category IDs
        $resourceCategories = DB::table('resource_categories')->pluck('id', 'slug');

        if ($resourceCategories->isEmpty()) {
            $this->command->info('No resource categories found, skipping resource seeding...');
            return;
        }

        $resources = [
            // Mental Health Resources
            [
                'category_id' => $resourceCategories['mental-health'] ?? null,
                'title' => 'Mindfulness Meditation for Beginners',
                'description' => 'Learn the basics of mindfulness meditation with this comprehensive guide designed specifically for students. This resource covers breathing techniques, body awareness, and simple practices you can do anywhere on campus.',
                'type' => 'video',
                'subcategory' => 'Mindfulness',
                'difficulty' => 'beginner',
                'duration' => '25 min',
                'external_url' => 'https://example.com/mindfulness-video',
                'thumbnail_url' => 'https://example.com/thumbnails/mindfulness.jpg',
                'tags' => ['meditation', 'stress relief', 'mindfulness', 'beginner'],
                'author_name' => 'Dr. Sarah Wilson',
                'author_bio' => 'Licensed therapist specializing in mindfulness-based interventions for students.',
                'rating' => 4.8,
                'is_published' => true,
                'is_featured' => true,
                'sort_order' => 1,
                'view_count' => rand(100, 2000),
                'download_count' => 0,
                'published_at' => now(),
            ],
            [
                'category_id' => $resourceCategories['mental-health'] ?? null,
                'title' => 'Breathing Exercises for Anxiety',
                'description' => 'Quick and effective breathing techniques to help manage anxiety and panic attacks. This audio guide provides step-by-step instructions for various breathing exercises you can use during stressful moments.',
                'type' => 'audio',
                'subcategory' => 'Anxiety Management',
                'difficulty' => 'beginner',
                'duration' => '15 min',
                'external_url' => 'https://example.com/breathing-exercises-audio',
                'tags' => ['anxiety', 'breathing', 'relaxation', 'quick relief'],
                'author_name' => 'Dr. Michael Chen',
                'author_bio' => 'Clinical psychologist with expertise in anxiety disorders and stress management.',
                'rating' => 4.9,
                'is_published' => true,
                'is_featured' => true,
                'sort_order' => 2,
                'view_count' => rand(100, 2000),
                'download_count' => 0,
                'published_at' => now(),
            ],
            [
                'category_id' => $resourceCategories['mental-health'] ?? null,
                'title' => 'Cognitive Behavioral Therapy Workbook',
                'description' => 'Interactive workbook with CBT exercises and techniques for managing negative thought patterns. Includes worksheets for thought recording, behavioral experiments, and mood tracking.',
                'type' => 'worksheet',
                'subcategory' => 'Therapy Techniques',
                'difficulty' => 'intermediate',
                'duration' => 'Self-paced',
                'external_url' => 'https://example.com/cbt-workbook-info',
                'download_url' => 'https://example.com/downloads/cbt-workbook.pdf',
                'tags' => ['CBT', 'therapy', 'mental health', 'self-help'],
                'author_name' => 'Clinical Psychology Team',
                'author_bio' => 'Licensed clinical psychologists specializing in CBT for college students.',
                'rating' => 4.8,
                'is_published' => true,
                'is_featured' => true,
                'sort_order' => 3,
                'view_count' => rand(100, 2000),
                'download_count' => rand(50, 1500),
                'published_at' => now(),
            ],
            [
                'category_id' => $resourceCategories['mental-health'] ?? null,
                'title' => 'Depression Self-Assessment Tool',
                'description' => 'A confidential self-assessment tool to help you understand your mental health and identify when professional support might be beneficial.',
                'type' => 'tool',
                'subcategory' => 'Assessment',
                'difficulty' => 'beginner',
                'duration' => '10 min',
                'external_url' => 'https://example.com/depression-assessment',
                'tags' => ['depression', 'assessment', 'self-help', 'screening'],
                'author_name' => 'Mental Health Team',
                'author_bio' => 'Licensed counselors and psychologists specializing in student mental health.',
                'rating' => 4.5,
                'is_published' => true,
                'is_featured' => false,
                'sort_order' => 4,
                'view_count' => rand(100, 2000),
                'download_count' => 0,
                'published_at' => now(),
            ],

            // Academic Success Resources
            [
                'category_id' => $resourceCategories['academic-success'] ?? null,
                'title' => 'Study Schedule Planner Template',
                'description' => 'Downloadable template to help you organize your study time and improve academic performance. Includes sections for goal setting, time blocking, and progress tracking.',
                'type' => 'worksheet',
                'subcategory' => 'Time Management',
                'difficulty' => 'beginner',
                'duration' => 'Self-paced',
                'external_url' => 'https://example.com/study-planner-info',
                'download_url' => 'https://example.com/downloads/study-planner.pdf',
                'tags' => ['planning', 'organization', 'study skills', 'templates'],
                'author_name' => 'Academic Success Team',
                'author_bio' => 'Educational specialists focused on helping students develop effective study strategies.',
                'rating' => 4.6,
                'is_published' => true,
                'is_featured' => true,
                'sort_order' => 1,
                'view_count' => rand(100, 2000),
                'download_count' => rand(50, 1500),
                'published_at' => now(),
            ],
            [
                'category_id' => $resourceCategories['academic-success'] ?? null,
                'title' => 'Effective Note-Taking Strategies',
                'description' => 'Comprehensive guide to various note-taking methods including Cornell notes, mind mapping, and digital tools. Learn how to capture and organize information effectively for better retention.',
                'type' => 'article',
                'subcategory' => 'Study Skills',
                'difficulty' => 'beginner',
                'duration' => '12 min read',
                'external_url' => 'https://example.com/note-taking-strategies',
                'tags' => ['note-taking', 'study skills', 'organization', 'retention'],
                'author_name' => 'Prof. Jennifer Adams',
                'author_bio' => 'Educational psychology professor with research focus on learning strategies.',
                'rating' => 4.7,
                'is_published' => true,
                'is_featured' => false,
                'sort_order' => 2,
                'view_count' => rand(100, 2000),
                'download_count' => 0,
                'published_at' => now(),
            ],
            [
                'category_id' => $resourceCategories['academic-success'] ?? null,
                'title' => 'Test Anxiety Management Guide',
                'description' => 'Strategies and techniques for managing test anxiety, including preparation methods, relaxation techniques, and cognitive strategies for exam success.',
                'type' => 'article',
                'subcategory' => 'Test Anxiety',
                'difficulty' => 'intermediate',
                'duration' => '18 min read',
                'external_url' => 'https://example.com/test-anxiety-guide',
                'tags' => ['test anxiety', 'exam stress', 'relaxation', 'study strategies'],
                'author_name' => 'Dr. Lisa Park',
                'author_bio' => 'Educational psychologist specializing in test anxiety and academic performance.',
                'rating' => 4.8,
                'is_published' => true,
                'is_featured' => true,
                'sort_order' => 3,
                'view_count' => rand(100, 2000),
                'download_count' => 0,
                'published_at' => now(),
            ],
            [
                'category_id' => $resourceCategories['academic-success'] ?? null,
                'title' => 'Procrastination Buster Toolkit',
                'description' => 'Practical tools and exercises to overcome procrastination, including time management techniques, motivation strategies, and accountability systems.',
                'type' => 'worksheet',
                'subcategory' => 'Productivity',
                'difficulty' => 'intermediate',
                'duration' => 'Self-paced',
                'external_url' => 'https://example.com/procrastination-toolkit-info',
                'download_url' => 'https://example.com/downloads/procrastination-toolkit.pdf',
                'tags' => ['procrastination', 'productivity', 'time management', 'motivation'],
                'author_name' => 'Productivity Coach Team',
                'author_bio' => 'Certified productivity coaches specializing in academic success.',
                'rating' => 4.4,
                'is_published' => true,
                'is_featured' => false,
                'sort_order' => 4,
                'view_count' => rand(100, 2000),
                'download_count' => rand(50, 1500),
                'published_at' => now(),
            ],

            // Social Wellness Resources
            [
                'category_id' => $resourceCategories['social-wellness'] ?? null,
                'title' => 'Building Healthy Relationships',
                'description' => 'Comprehensive guide to developing and maintaining healthy relationships during college years. Covers communication skills, boundary setting, and conflict resolution.',
                'type' => 'article',
                'subcategory' => 'Relationships',
                'difficulty' => 'intermediate',
                'duration' => '20 min read',
                'external_url' => 'https://example.com/healthy-relationships',
                'tags' => ['relationships', 'communication', 'social skills', 'college life'],
                'author_name' => 'Dr. Emily Rodriguez',
                'author_bio' => 'Licensed marriage and family therapist specializing in young adult relationships.',
                'rating' => 4.7,
                'is_published' => true,
                'is_featured' => false,
                'sort_order' => 1,
                'view_count' => rand(100, 2000),
                'download_count' => 0,
                'published_at' => now(),
            ],
            [
                'category_id' => $resourceCategories['social-wellness'] ?? null,
                'title' => 'Communication Skills Workshop',
                'description' => 'Interactive video workshop covering assertive communication, active listening, and expressing emotions effectively. Perfect for improving personal and academic relationships.',
                'type' => 'video',
                'subcategory' => 'Communication',
                'difficulty' => 'intermediate',
                'duration' => '45 min',
                'external_url' => 'https://example.com/communication-workshop',
                'thumbnail_url' => 'https://example.com/thumbnails/communication.jpg',
                'tags' => ['communication', 'workshop', 'social skills', 'relationships'],
                'author_name' => 'Dr. Marcus Thompson',
                'author_bio' => 'Communication specialist and licensed counselor with 15 years of experience.',
                'rating' => 4.5,
                'is_published' => true,
                'is_featured' => false,
                'sort_order' => 2,
                'view_count' => rand(100, 2000),
                'download_count' => 0,
                'published_at' => now(),
            ],
            [
                'category_id' => $resourceCategories['social-wellness'] ?? null,
                'title' => 'Overcoming Social Anxiety',
                'description' => 'Practical strategies for managing social anxiety in college settings, including exposure techniques, cognitive restructuring, and building confidence.',
                'type' => 'video',
                'subcategory' => 'Social Anxiety',
                'difficulty' => 'intermediate',
                'duration' => '35 min',
                'external_url' => 'https://example.com/social-anxiety-video',
                'thumbnail_url' => 'https://example.com/thumbnails/social-anxiety.jpg',
                'tags' => ['social anxiety', 'confidence', 'college life', 'exposure therapy'],
                'author_name' => 'Dr. Amanda Clark',
                'author_bio' => 'Clinical psychologist specializing in anxiety disorders and social skills.',
                'rating' => 4.6,
                'is_published' => true,
                'is_featured' => true,
                'sort_order' => 3,
                'view_count' => rand(100, 2000),
                'download_count' => 0,
                'published_at' => now(),
            ],

            // Physical Wellness Resources
            [
                'category_id' => $resourceCategories['physical-wellness'] ?? null,
                'title' => 'Sleep Hygiene for Students',
                'description' => 'Evidence-based strategies to improve sleep quality and establish healthy sleep habits. Learn about sleep cycles, environmental factors, and practical tips for better rest.',
                'type' => 'video',
                'subcategory' => 'Sleep Health',
                'difficulty' => 'beginner',
                'duration' => '18 min',
                'external_url' => 'https://example.com/sleep-hygiene-video',
                'thumbnail_url' => 'https://example.com/thumbnails/sleep.jpg',
                'tags' => ['sleep', 'health', 'wellness', 'habits'],
                'author_name' => 'Wellness Center',
                'author_bio' => 'Multidisciplinary team of health professionals specializing in student wellness.',
                'rating' => 4.5,
                'is_published' => true,
                'is_featured' => false,
                'sort_order' => 1,
                'view_count' => rand(100, 2000),
                'download_count' => 0,
                'published_at' => now(),
            ],
            [
                'category_id' => $resourceCategories['physical-wellness'] ?? null,
                'title' => 'Progressive Muscle Relaxation',
                'description' => 'Guided audio session for deep relaxation and stress relief through progressive muscle relaxation. Learn to systematically tense and release muscle groups for ultimate relaxation.',
                'type' => 'audio',
                'subcategory' => 'Stress Management',
                'difficulty' => 'beginner',
                'duration' => '30 min',
                'external_url' => 'https://example.com/muscle-relaxation-audio',
                'tags' => ['relaxation', 'stress relief', 'muscle tension', 'guided'],
                'author_name' => 'Dr. Sarah Wilson',
                'author_bio' => 'Licensed therapist specializing in mindfulness-based interventions for students.',
                'rating' => 4.7,
                'is_published' => true,
                'is_featured' => false,
                'sort_order' => 2,
                'view_count' => rand(100, 2000),
                'download_count' => 0,
                'published_at' => now(),
            ],
            [
                'category_id' => $resourceCategories['physical-wellness'] ?? null,
                'title' => 'Nutrition Guide for Busy Students',
                'description' => 'Practical nutrition advice for college students, including meal planning, healthy snacking, and budget-friendly nutrition tips.',
                'type' => 'article',
                'subcategory' => 'Nutrition',
                'difficulty' => 'beginner',
                'duration' => '15 min read',
                'external_url' => 'https://example.com/nutrition-guide',
                'tags' => ['nutrition', 'meal planning', 'health', 'budget'],
                'author_name' => 'Campus Nutritionist',
                'author_bio' => 'Registered dietitian specializing in college student nutrition.',
                'rating' => 4.3,
                'is_published' => true,
                'is_featured' => false,
                'sort_order' => 3,
                'view_count' => rand(100, 2000),
                'download_count' => 0,
                'published_at' => now(),
            ],

            // Life Skills Resources
            [
                'category_id' => $resourceCategories['life-skills'] ?? null,
                'title' => 'Financial Wellness for Students',
                'description' => 'Practical tools and strategies for managing finances and reducing money-related stress. Includes budgeting templates, debt management tips, and financial planning resources.',
                'type' => 'tool',
                'subcategory' => 'Financial Management',
                'difficulty' => 'intermediate',
                'duration' => 'Interactive',
                'external_url' => 'https://example.com/financial-wellness-tool',
                'tags' => ['finance', 'budgeting', 'money management', 'stress'],
                'author_name' => 'Student Services',
                'author_bio' => 'Financial counselors and student support specialists.',
                'rating' => 4.4,
                'is_published' => true,
                'is_featured' => false,
                'sort_order' => 1,
                'view_count' => rand(100, 2000),
                'download_count' => 0,
                'published_at' => now(),
            ],
            [
                'category_id' => $resourceCategories['life-skills'] ?? null,
                'title' => 'Time Management Mastery',
                'description' => 'Comprehensive course on mastering time management skills. Learn prioritization techniques, goal setting, and productivity systems that work for busy students.',
                'type' => 'video',
                'subcategory' => 'Productivity',
                'difficulty' => 'intermediate',
                'duration' => '1 hour',
                'external_url' => 'https://example.com/time-management-course',
                'thumbnail_url' => 'https://example.com/thumbnails/time-management.jpg',
                'tags' => ['time management', 'productivity', 'goal setting', 'organization'],
                'author_name' => 'Dr. Lisa Park',
                'author_bio' => 'Productivity coach and educational consultant specializing in student success strategies.',
                'rating' => 4.6,
                'is_published' => true,
                'is_featured' => false,
                'sort_order' => 2,
                'view_count' => rand(100, 2000),
                'download_count' => 0,
                'published_at' => now(),
            ],
            [
                'category_id' => $resourceCategories['life-skills'] ?? null,
                'title' => 'Decision Making Framework',
                'description' => 'Learn a systematic approach to making important life decisions, including pros and cons analysis, values clarification, and decision-making tools.',
                'type' => 'worksheet',
                'subcategory' => 'Decision Making',
                'difficulty' => 'intermediate',
                'duration' => 'Self-paced',
                'external_url' => 'https://example.com/decision-making-info',
                'download_url' => 'https://example.com/downloads/decision-framework.pdf',
                'tags' => ['decision making', 'problem solving', 'life skills', 'framework'],
                'author_name' => 'Life Skills Team',
                'author_bio' => 'Counselors and coaches specializing in personal development.',
                'rating' => 4.2,
                'is_published' => true,
                'is_featured' => false,
                'sort_order' => 3,
                'view_count' => rand(100, 2000),
                'download_count' => rand(50, 1500),
                'published_at' => now(),
            ],

            // Crisis Resources
            [
                'category_id' => $resourceCategories['crisis-resources'] ?? null,
                'title' => 'Crisis Support Hotlines and Resources',
                'description' => 'Comprehensive list of crisis support hotlines, emergency contacts, and immediate help resources. Available 24/7 for students in crisis situations.',
                'type' => 'article',
                'subcategory' => 'Emergency Resources',
                'difficulty' => 'beginner',
                'duration' => '5 min read',
                'external_url' => 'https://example.com/crisis-resources',
                'tags' => ['crisis', 'emergency', 'hotlines', 'support'],
                'author_name' => 'Crisis Intervention Team',
                'author_bio' => 'Licensed crisis counselors and mental health professionals.',
                'rating' => 5.0,
                'is_published' => true,
                'is_featured' => true,
                'sort_order' => 1,
                'view_count' => rand(100, 2000),
                'download_count' => 0,
                'published_at' => now(),
            ],
            [
                'category_id' => $resourceCategories['crisis-resources'] ?? null,
                'title' => 'Safety Planning Worksheet',
                'description' => 'Interactive worksheet to help create a personalized safety plan for managing crisis situations. Includes coping strategies, support contacts, and warning signs identification.',
                'type' => 'worksheet',
                'subcategory' => 'Safety Planning',
                'difficulty' => 'intermediate',
                'duration' => 'Self-paced',
                'external_url' => 'https://example.com/safety-planning-info',
                'download_url' => 'https://example.com/downloads/safety-plan.pdf',
                'tags' => ['safety planning', 'crisis', 'coping strategies', 'emergency'],
                'author_name' => 'Crisis Intervention Team',
                'author_bio' => 'Licensed crisis counselors and mental health professionals.',
                'rating' => 4.9,
                'is_published' => true,
                'is_featured' => true,
                'sort_order' => 2,
                'view_count' => rand(100, 2000),
                'download_count' => rand(50, 1500),
                'published_at' => now(),
            ],
            [
                'category_id' => $resourceCategories['crisis-resources'] ?? null,
                'title' => 'Recognizing Warning Signs',
                'description' => 'Learn to identify warning signs of mental health crisis in yourself and others, plus steps to take when you notice these signs.',
                'type' => 'video',
                'subcategory' => 'Prevention',
                'difficulty' => 'beginner',
                'duration' => '22 min',
                'external_url' => 'https://example.com/warning-signs-video',
                'thumbnail_url' => 'https://example.com/thumbnails/warning-signs.jpg',
                'tags' => ['warning signs', 'prevention', 'mental health', 'crisis'],
                'author_name' => 'Dr. Robert Kim',
                'author_bio' => 'Crisis intervention specialist with expertise in suicide prevention.',
                'rating' => 4.8,
                'is_published' => true,
                'is_featured' => false,
                'sort_order' => 3,
                'view_count' => rand(100, 2000),
                'download_count' => 0,
                'published_at' => now(),
            ],
        ];

        foreach ($resources as $resourceData) {
            // Skip if category doesn't exist
            if (is_null($resourceData['category_id'])) {
                continue;
            }

            $resourceData['slug'] = Str::slug($resourceData['title']) . '-' . time() . '-' . rand(1000, 9999);
            $resourceData['created_by'] = $admin->id;
            $resourceData['tags'] = json_encode($resourceData['tags']);
            $resourceData['created_at'] = now();
            $resourceData['updated_at'] = now();

            DB::table('resources')->insert($resourceData);
        }

        $this->command->info('✅ Seeded comprehensive resources');
    }
}