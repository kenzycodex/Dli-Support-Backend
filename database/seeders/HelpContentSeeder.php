<?php
// database/seeders/HelpContentSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\HelpCategory;
use App\Models\FAQ;
use App\Models\ResourceCategory;
use App\Models\Resource;
use App\Models\User;
use Illuminate\Support\Str;

class HelpContentSeeder extends Seeder
{
    public function run()
    {
        // Get an admin user for content creation
        $admin = User::where('role', 'admin')->first();
        if (!$admin) {
            $admin = User::factory()->create(['role' => 'admin']);
        }

        // Seed Sample FAQs
        $this->seedFAQs($admin);
        
        // Seed Sample Resources
        $this->seedResources($admin);
    }

    private function seedFAQs($admin)
    {
        $appointmentsCategory = HelpCategory::where('slug', 'appointments')->first();
        $technicalCategory = HelpCategory::where('slug', 'technical')->first();
        $privacyCategory = HelpCategory::where('slug', 'privacy')->first();
        $crisisCategory = HelpCategory::where('slug', 'crisis')->first();
        $servicesCategory = HelpCategory::where('slug', 'services')->first();

        $faqs = [
            // Appointments FAQs
            [
                'category_id' => $appointmentsCategory->id,
                'question' => 'How do I book an appointment with a counselor?',
                'answer' => 'You can book an appointment by navigating to the "Appointments" section in your dashboard. Click "Book Appointment", choose your preferred type (video, phone, or in-person), select an available counselor, pick a date and time that works for you, and confirm your booking. You\'ll receive a confirmation email with all the details.',
                'tags' => ['booking', 'appointment', 'counselor', 'schedule'],
                'is_published' => true,
                'is_featured' => true,
                'sort_order' => 1,
                'published_at' => now(),
            ],
            [
                'category_id' => $appointmentsCategory->id,
                'question' => 'Can I cancel or reschedule my appointment?',
                'answer' => 'Yes, you can cancel or reschedule appointments up to 24 hours before the scheduled time. Go to your "My Appointments" page, find the appointment you want to change, and click either "Reschedule" or "Cancel". Please note that late cancellations (less than 24 hours) may be subject to our cancellation policy.',
                'tags' => ['cancel', 'reschedule', 'appointment', 'policy'],
                'is_published' => true,
                'is_featured' => false,
                'sort_order' => 2,
                'published_at' => now(),
            ],
            [
                'category_id' => $appointmentsCategory->id,
                'question' => 'What should I expect during my first counseling session?',
                'answer' => 'Your first session will typically involve getting to know your counselor, discussing your concerns and goals, and developing a plan for future sessions. It\'s normal to feel nervous - your counselor will help you feel comfortable. The session usually lasts 50 minutes, and everything discussed is confidential.',
                'tags' => ['first session', 'expectations', 'counseling', 'confidential'],
                'is_published' => true,
                'is_featured' => true,
                'sort_order' => 3,
                'published_at' => now(),
            ],

            // Technical FAQs
            [
                'category_id' => $technicalCategory->id,
                'question' => 'How do I reset my password?',
                'answer' => 'To reset your password, click the "Forgot Password" link on the login page. Enter your email address and you\'ll receive a password reset link within a few minutes. Follow the instructions in the email to create a new password. If you don\'t receive the email, check your spam folder or contact support.',
                'tags' => ['password', 'reset', 'login', 'account'],
                'is_published' => true,
                'is_featured' => false,
                'sort_order' => 1,
                'published_at' => now(),
            ],
            [
                'category_id' => $technicalCategory->id,
                'question' => 'Why can\'t I access video sessions?',
                'answer' => 'Video session issues are usually related to browser permissions or internet connectivity. Make sure you\'ve allowed camera and microphone access in your browser settings. We recommend using Chrome or Firefox for the best experience. If problems persist, try refreshing the page or contact our technical support team.',
                'tags' => ['video', 'technical', 'browser', 'permissions'],
                'is_published' => true,
                'is_featured' => false,
                'sort_order' => 2,
                'published_at' => now(),
            ],

            // Privacy FAQs
            [
                'category_id' => $privacyCategory->id,
                'question' => 'Is my information kept confidential?',
                'answer' => 'Yes, absolutely. All your personal information, session notes, and communications are kept strictly confidential in accordance with HIPAA regulations and our privacy policy. Information is only shared with your explicit consent or in cases where there\'s immediate danger to yourself or others.',
                'tags' => ['confidential', 'privacy', 'HIPAA', 'security'],
                'is_published' => true,
                'is_featured' => true,
                'sort_order' => 1,
                'published_at' => now(),
            ],
            [
                'category_id' => $privacyCategory->id,
                'question' => 'Who can see my appointment history?',
                'answer' => 'Your appointment history is only visible to you and your assigned counselors. Administrative staff may have limited access for scheduling purposes only. We follow strict data protection protocols to ensure your privacy is maintained at all times.',
                'tags' => ['appointment history', 'privacy', 'access', 'data protection'],
                'is_published' => true,
                'is_featured' => false,
                'sort_order' => 2,
                'published_at' => now(),
            ],

            // Crisis FAQs
            [
                'category_id' => $crisisCategory->id,
                'question' => 'What should I do if I\'m having a mental health crisis?',
                'answer' => 'If you\'re experiencing a mental health crisis, please reach out immediately. Use our Crisis Support button for 24/7 assistance, call the National Suicide Prevention Lifeline at 988, or contact emergency services at 911 if you\'re in immediate danger. You\'re not alone, and help is always available.',
                'tags' => ['crisis', 'emergency', 'suicide prevention', '988', 'help'],
                'is_published' => true,
                'is_featured' => true,
                'sort_order' => 1,
                'published_at' => now(),
            ],

            // Services FAQs
            [
                'category_id' => $servicesCategory->id,
                'question' => 'What types of counseling services are available?',
                'answer' => 'We offer individual counseling, group therapy, crisis intervention, academic counseling, and specialized support for anxiety, depression, stress management, and relationship issues. All services are provided by licensed professionals who specialize in working with students.',
                'tags' => ['services', 'counseling types', 'therapy', 'specializations'],
                'is_published' => true,
                'is_featured' => true,
                'sort_order' => 1,
                'published_at' => now(),
            ],
            [
                'category_id' => $servicesCategory->id,
                'question' => 'Are counseling services free for students?',
                'answer' => 'Yes, all basic counseling services are provided free of charge to enrolled students. This includes individual sessions, group therapy, crisis support, and access to our resource library. Some specialized programs may have limited availability, but we strive to ensure all students have access to the support they need.',
                'tags' => ['free', 'cost', 'students', 'services'],
                'is_published' => true,
                'is_featured' => false,
                'sort_order' => 2,
                'published_at' => now(),
            ],
        ];

        foreach ($faqs as $faqData) {
            $faqData['slug'] = Str::slug($faqData['question']) . '-' . time() . '-' . rand(1000, 9999);
            $faqData['created_by'] = $admin->id;
            FAQ::create($faqData);
        }
    }

    private function seedResources($admin)
    {
        $mentalHealthCategory = ResourceCategory::where('slug', 'mental-health')->first();
        $academicSuccessCategory = ResourceCategory::where('slug', 'academic-success')->first();
        $socialWellnessCategory = ResourceCategory::where('slug', 'social-wellness')->first();
        $physicalWellnessCategory = ResourceCategory::where('slug', 'physical-wellness')->first();
        $lifeSkillsCategory = ResourceCategory::where('slug', 'life-skills')->first();
        $crisisResourcesCategory = ResourceCategory::where('slug', 'crisis-resources')->first();

        $resources = [
            // Mental Health Resources
            [
                'category_id' => $mentalHealthCategory->id,
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
                'published_at' => now(),
            ],
            [
                'category_id' => $mentalHealthCategory->id,
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
                'published_at' => now(),
            ],
            [
                'category_id' => $mentalHealthCategory->id,
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
                'published_at' => now(),
            ],

            // Academic Success Resources
            [
                'category_id' => $academicSuccessCategory->id,
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
                'published_at' => now(),
            ],
            [
                'category_id' => $academicSuccessCategory->id,
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
                'published_at' => now(),
            ],

            // Social Wellness Resources
            [
                'category_id' => $socialWellnessCategory->id,
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
                'published_at' => now(),
            ],
            [
                'category_id' => $socialWellnessCategory->id,
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
                'published_at' => now(),
            ],

            // Physical Wellness Resources
            [
                'category_id' => $physicalWellnessCategory->id,
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
                'published_at' => now(),
            ],
            [
                'category_id' => $physicalWellnessCategory->id,
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
                'published_at' => now(),
            ],

            // Life Skills Resources
            [
                'category_id' => $lifeSkillsCategory->id,
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
                'published_at' => now(),
            ],
            [
                'category_id' => $lifeSkillsCategory->id,
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
                'published_at' => now(),
            ],

            // Crisis Resources
            [
                'category_id' => $crisisResourcesCategory->id,
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
                'published_at' => now(),
            ],
            [
                'category_id' => $crisisResourcesCategory->id,
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
                'published_at' => now(),
            ],
        ];

        foreach ($resources as $resourceData) {
            $resourceData['slug'] = Str::slug($resourceData['title']) . '-' . time() . '-' . rand(1000, 9999);
            $resourceData['created_by'] = $admin->id;
            
            // Add some random view counts and download counts for demo purposes
            $resourceData['view_count'] = rand(50, 2000);
            if ($resourceData['type'] === 'worksheet') {
                $resourceData['download_count'] = rand(25, 1500);
            } else {
                $resourceData['download_count'] = 0;
            }
            
            Resource::create($resourceData);
        }
    }
}