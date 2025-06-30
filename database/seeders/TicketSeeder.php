<?php
// database/seeders/TicketSeeder.php (Enhanced with new model features)

namespace Database\Seeders;

use App\Models\User;
use App\Models\Ticket;
use App\Models\TicketResponse;
use App\Models\TicketAttachment;
use App\Models\Notification;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TicketSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🎫 Creating comprehensive ticket test data...');
        
        // Get users for different roles
        $users = $this->getTestUsers();
        
        if (empty($users)) {
            $this->command->error('❌ No test users found. Please run UserSeeder first.');
            return;
        }

        // Create tickets for different scenarios
        $this->createStudentTickets($users);
        $this->createCrisisTickets($users);
        $this->createUnassignedTickets($users);
        $this->createResolvedTickets($users);
        $this->createVariousCategoryTickets($users);
        
        $this->command->info('✅ Enhanced ticket seeding completed successfully!');
        $this->printSeedingSummary();
    }

    private function getTestUsers(): array
    {
        return [
            'students' => User::where('role', 'student')->take(5)->get(),
            'counselors' => User::where('role', 'counselor')->get(),
            'advisors' => User::where('role', 'advisor')->get(),
            'admins' => User::where('role', 'admin')->get(),
        ];
    }

    private function createStudentTickets(array $users): void
    {
        $this->command->info('📚 Creating student tickets...');
        
        $student1 = $users['students']->first();
        $counselor = $users['counselors']->first();
        $advisor = $users['advisors']->first();

        if (!$student1 || !$counselor) return;

        // Technical Issue Ticket (In Progress with tags)
        $ticket1 = Ticket::create([
            'user_id' => $student1->id,
            'subject' => 'Unable to access course materials for Psychology 101',
            'description' => 'I am having trouble accessing the course materials for Psychology 101. When I click on the link, it shows an error message saying "Access Denied". This has been happening for the past 2 days and I need to complete my assignment that\'s due tomorrow.',
            'category' => 'technical',
            'priority' => 'High',
            'status' => 'In Progress',
            'assigned_to' => $counselor->id,
            'tags' => ['urgent', 'reviewed'],
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subHours(4),
        ]);

        $this->addConversationThread($ticket1, [
            [
                'user_id' => $student1->id,
                'message' => 'I am having trouble accessing the course materials for Psychology 101. When I click on the link, it shows an error message saying "Access Denied". This has been happening for the past 2 days and I need to complete my assignment.',
                'is_internal' => false,
                'created_at' => now()->subDays(2),
            ],
            [
                'user_id' => $counselor->id,
                'message' => 'Thank you for reporting this issue. I can see the problem and have escalated it to our technical team. You should have access restored within 24 hours. I will keep you updated on the progress.',
                'is_internal' => false,
                'created_at' => now()->subDays(1)->subHours(8),
            ],
            [
                'user_id' => $counselor->id,
                'message' => 'Student reports technical issue with Psychology 101 course materials. Escalated to IT department. Priority: High due to assignment deadline tomorrow.',
                'is_internal' => true,
                'visibility' => 'counselors',
                'created_at' => now()->subDays(1)->subHours(6),
            ],
            [
                'user_id' => $counselor->id,
                'message' => 'Good news! Our technical team has identified and fixed the issue. The course materials should now be accessible. Please try logging in again and let me know if you continue to experience any problems.',
                'is_internal' => false,
                'created_at' => now()->subHours(4),
            ],
        ]);

        // Academic Planning Ticket (Open)
        if ($advisor) {
            $student2 = $users['students']->skip(1)->first();
            if ($student2) {
                $ticket2 = Ticket::create([
                    'user_id' => $student2->id,
                    'subject' => 'Course selection guidance for Computer Science major',
                    'description' => 'I need help selecting courses for the spring semester. I want to make sure I am on track for graduation and taking the right prerequisites for my Computer Science major. I am particularly confused about the math requirements and which electives would be most beneficial for my career goals in software development.',
                    'category' => 'academic',
                    'priority' => 'Medium',
                    'status' => 'Open',
                    'assigned_to' => $advisor->id,
                    'tags' => ['follow-up'],
                    'created_at' => now()->subDays(1),
                    'updated_at' => now()->subDays(1),
                ]);

                $this->addConversationThread($ticket2, [
                    [
                        'user_id' => $student2->id,
                        'message' => 'I need help selecting courses for the spring semester. I want to make sure I am on track for graduation and taking the right prerequisites for my Computer Science major. I am particularly confused about the math requirements and which electives would be most beneficial for my career goals in software development.',
                        'is_internal' => false,
                        'created_at' => now()->subDays(1),
                    ],
                ]);
            }
        }
    }

    private function createCrisisTickets(array $users): void
    {
        $this->command->info('🚨 Creating crisis tickets...');
        
        $student = $users['students']->skip(2)->first();
        $counselor = $users['counselors']->first();

        if (!$student || !$counselor) return;

        // Crisis Mental Health Ticket
        $crisisTicket = Ticket::create([
            'user_id' => $student->id,
            'subject' => 'Feeling overwhelmed with academic pressure',
            'description' => 'I have been feeling extremely overwhelmed lately with all my courses and assignments. I feel hopeless and like I cannot keep up anymore. Sometimes I feel like I want to give up entirely and that there\'s no point in continuing. I really need someone to talk to about these feelings because I don\'t know what to do.',
            'category' => 'mental-health',
            'priority' => 'Urgent',
            'status' => 'In Progress',
            'assigned_to' => $counselor->id,
            'crisis_flag' => true,
            'tags' => ['urgent', 'escalated'],
            'created_at' => now()->subHours(6),
            'updated_at' => now()->subHours(2),
        ]);

        $this->addConversationThread($crisisTicket, [
            [
                'user_id' => $student->id,
                'message' => 'I have been feeling extremely overwhelmed lately with all my courses and assignments. I feel hopeless and like I cannot keep up anymore. Sometimes I feel like I want to give up entirely and that there\'s no point in continuing. I really need someone to talk to about these feelings because I don\'t know what to do.',
                'is_internal' => false,
                'created_at' => now()->subHours(6),
            ],
            [
                'user_id' => $counselor->id,
                'message' => 'Thank you for reaching out - that took real courage. I want you to know that you are not alone and these feelings are completely valid. I understand how overwhelming everything can feel right now. I have immediately scheduled an urgent appointment for you today at 3:00 PM. Please call our crisis support line at (555) 123-4567 if you need immediate support before then.',
                'is_internal' => false,
                'created_at' => now()->subHours(5)->subMinutes(30),
            ],
            [
                'user_id' => $counselor->id,
                'message' => '🚨 CRISIS FLAG: Student expressing hopelessness and potential suicidal ideation. Immediate intervention scheduled for 3:00 PM today. Following up with emergency protocols. Supervisor notified. Student provided with crisis hotline number.',
                'is_internal' => true,
                'visibility' => 'counselors',
                'is_urgent' => true,
                'created_at' => now()->subHours(5),
            ],
            [
                'user_id' => $student->id,
                'message' => 'Thank you so much for responding so quickly. I really appreciate having someone to talk to. I will be there for the appointment at 3:00 PM.',
                'is_internal' => false,
                'created_at' => now()->subHours(3),
            ],
        ]);

        // Create notifications for crisis ticket
        $this->createCrisisNotifications($crisisTicket, $users);
    }

    private function createUnassignedTickets(array $users): void
    {
        $this->command->info('⏳ Creating unassigned tickets...');
        
        $students = $users['students'];

        // Technical issue - unassigned
        if ($students->count() > 3) {
            Ticket::create([
                'user_id' => $students->skip(3)->first()->id,
                'subject' => 'Library access card not working after hours',
                'description' => 'My student ID card is not working to access the library after hours. I tried multiple times but the scanner does not recognize it. This is preventing me from accessing study materials for my upcoming exams.',
                'category' => Ticket::CATEGORY_TECHNICAL,
                'priority' => Ticket::PRIORITY_MEDIUM,
                'status' => Ticket::STATUS_OPEN,
                'created_at' => now()->subHours(3),
                'updated_at' => now()->subHours(3),
            ]);
        }

        // Administrative issue - unassigned
        if ($students->count() > 4) {
            Ticket::create([
                'user_id' => $students->skip(4)->first()->id,
                'subject' => 'Financial aid documentation requirements unclear',
                'description' => 'I received a request for additional financial aid documentation but I am not sure what specific documents they need. The email was not very clear about the requirements and deadline.',
                'category' => Ticket::CATEGORY_OTHER,
                'priority' => Ticket::PRIORITY_HIGH,
                'status' => Ticket::STATUS_OPEN,
                'created_at' => now()->subMinutes(45),
                'updated_at' => now()->subMinutes(45),
            ]);
        }
    }

    private function createResolvedTickets(array $users): void
    {
        $this->command->info('✅ Creating resolved tickets...');
        
        $student = $users['students']->skip(1)->first();
        $counselor = $users['counselors']->first();

        if (!$student || !$counselor) return;

        // Resolved Administrative Ticket
        $resolvedTicket = Ticket::create([
            'user_id' => $student->id,
            'subject' => 'Transcript request processing delay',
            'description' => 'I submitted a transcript request 3 weeks ago for graduate school applications, but I have not received any updates. The deadline for my applications is approaching and I need the transcripts urgently.',
            'category' => Ticket::CATEGORY_OTHER,
            'priority' => Ticket::PRIORITY_HIGH,
            'status' => Ticket::STATUS_RESOLVED,
            'assigned_to' => $counselor->id,
            'tags' => [Ticket::TAG_REVIEWED, Ticket::TAG_FOLLOW_UP],
            'resolved_at' => now()->subDays(2),
            'created_at' => now()->subWeeks(2),
            'updated_at' => now()->subDays(2),
        ]);

        $this->addConversationThread($resolvedTicket, [
            [
                'user_id' => $student->id,
                'message' => 'I submitted a transcript request 3 weeks ago for graduate school applications, but I have not received any updates. The deadline for my applications is approaching and I need the transcripts urgently.',
                'is_internal' => false,
                'created_at' => now()->subWeeks(2),
            ],
            [
                'user_id' => $counselor->id,
                'message' => 'I understand your concern about the transcript delay. I have immediately contacted the registrar office about your request. They confirmed it is being expedited and will be completed within 2 business days. You will receive an email confirmation once it is sent to your graduate schools.',
                'is_internal' => false,
                'created_at' => now()->subDays(5),
            ],
            [
                'user_id' => $student->id,
                'message' => 'Perfect! I received the transcript confirmation email this morning. All my graduate schools have received the transcripts. Thank you so much for following up on this and getting it expedited!',
                'is_internal' => false,
                'created_at' => now()->subDays(2),
            ],
        ]);

        // Closed Ticket
        $closedTicket = Ticket::create([
            'user_id' => $student->id,
            'subject' => 'Parking permit renewal process question',
            'description' => 'My parking permit expires next month and I am not sure how to renew it. The university website is confusing and I cannot find the renewal form anywhere.',
            'category' => Ticket::CATEGORY_OTHER,
            'priority' => Ticket::PRIORITY_LOW,
            'status' => Ticket::STATUS_CLOSED,
            'assigned_to' => $counselor->id,
            'resolved_at' => now()->subDays(5),
            'closed_at' => now()->subDays(4),
            'created_at' => now()->subWeek(),
            'updated_at' => now()->subDays(4),
        ]);

        $this->addConversationThread($closedTicket, [
            [
                'user_id' => $student->id,
                'message' => 'My parking permit expires next month and I am not sure how to renew it. The university website is confusing and I cannot find the renewal form anywhere.',
                'is_internal' => false,
                'created_at' => now()->subWeek(),
            ],
            [
                'user_id' => $counselor->id,
                'message' => 'You can renew your parking permit online at parking.university.edu/renew or visit the parking office in the Student Services building, Room 150. The renewal form is under the "Current Students" section. You can also renew in person with cash, check, or card. Let me know if you need any other assistance!',
                'is_internal' => false,
                'created_at' => now()->subDays(6),
            ],
            [
                'user_id' => $student->id,
                'message' => 'Found it! Thank you for the direct link and the detailed instructions. I was able to renew online successfully. Really appreciate your help!',
                'is_internal' => false,
                'created_at' => now()->subDays(5),
            ],
        ]);
    }

    private function createVariousCategoryTickets(array $users): void
    {
        $this->command->info('📂 Creating tickets for all categories...');
        
        $students = $users['students'];
        $counselors = $users['counselors'];
        $advisors = $users['advisors'];

        // General Inquiry Ticket
        if ($students->count() > 0 && $advisors->count() > 0) {
            $ticket = Ticket::create([
                'user_id' => $students->first()->id,
                'subject' => 'Campus recreation center hours and facilities',
                'description' => 'I would like to know the current hours for the campus recreation center and what facilities are available. I am specifically interested in the swimming pool schedule and group fitness classes.',
                'category' => Ticket::CATEGORY_GENERAL,
                'priority' => Ticket::PRIORITY_LOW,
                'status' => Ticket::STATUS_OPEN,
                'assigned_to' => $advisors->first()->id,
                'created_at' => now()->subHours(12),
                'updated_at' => now()->subHours(12),
            ]);

            TicketResponse::create([
                'ticket_id' => $ticket->id,
                'user_id' => $students->first()->id,
                'message' => 'I would like to know the current hours for the campus recreation center and what facilities are available. I am specifically interested in the swimming pool schedule and group fitness classes.',
                'is_internal' => false,
                'created_at' => now()->subHours(12),
            ]);
        }

        // Academic Help Ticket with High Priority
        if ($students->count() > 1 && $advisors->count() > 0) {
            $ticket = Ticket::create([
                'user_id' => $students->skip(1)->first()->id,
                'subject' => 'Struggling with Advanced Calculus concepts',
                'description' => 'I am having difficulty understanding integration by parts and partial fractions in my Advanced Calculus course. The midterm exam is next week and I need additional tutoring or study resources. My current grade is concerning and I want to improve before the final.',
                'category' => Ticket::CATEGORY_ACADEMIC,
                'priority' => Ticket::PRIORITY_HIGH,
                'status' => Ticket::STATUS_IN_PROGRESS,
                'assigned_to' => $advisors->first()->id,
                'tags' => [Ticket::TAG_URGENT],
                'created_at' => now()->subDays(3),
                'updated_at' => now()->subHours(8),
            ]);

            $this->addConversationThread($ticket, [
                [
                    'user_id' => $students->skip(1)->first()->id,
                    'message' => 'I am having difficulty understanding integration by parts and partial fractions in my Advanced Calculus course. The midterm exam is next week and I need additional tutoring or study resources.',
                    'is_internal' => false,
                    'created_at' => now()->subDays(3),
                ],
                [
                    'user_id' => $advisors->first()->id,
                    'message' => 'I understand your concern about the upcoming midterm. I have scheduled you for tutoring sessions with our Math Learning Center. You can attend drop-in sessions MWF 2-4 PM or schedule individual appointments. I am also sending you some additional practice problems and video resources.',
                    'is_internal' => false,
                    'created_at' => now()->subDays(2),
                ],
            ]);
        }

        // Create some tagged tickets for demonstration
        if ($students->count() > 2 && $counselors->count() > 0) {
            Ticket::create([
                'user_id' => $students->skip(2)->first()->id,
                'subject' => 'Need study space accommodations for exam period',
                'description' => 'I have ADHD and need a quiet study space during the upcoming final exam period. The library gets very crowded and noisy, which makes it difficult for me to concentrate.',
                'category' => Ticket::CATEGORY_OTHER,
                'priority' => Ticket::PRIORITY_MEDIUM,
                'status' => Ticket::STATUS_OPEN,
                'assigned_to' => $counselors->first()->id,
                'tags' => [Ticket::TAG_FOLLOW_UP, Ticket::TAG_REVIEWED],
                'created_at' => now()->subHours(8),
                'updated_at' => now()->subHours(8),
            ]);
        }
    }

    private function addConversationThread(Ticket $ticket, array $responses): void
    {
        foreach ($responses as $responseData) {
            TicketResponse::create(array_merge([
                'ticket_id' => $ticket->id,
                'visibility' => 'all',
                'is_urgent' => false,
            ], $responseData));
        }
    }

    private function createCrisisNotifications(Ticket $ticket, array $users): void
    {
        // Notify all counselors about crisis ticket
        foreach ($users['counselors'] as $counselor) {
            Notification::create([
                'user_id' => $counselor->id,
                'type' => 'ticket',
                'title' => '🚨 CRISIS TICKET - Immediate Attention Required',
                'message' => "Crisis ticket #{$ticket->ticket_number} created. Student expressing hopelessness and potential self-harm ideation. Immediate intervention required.",
                'priority' => 'high',
                'read' => $counselor->id === $ticket->assigned_to, // Mark as unread for non-assigned counselors
                'data' => json_encode([
                    'ticket_id' => $ticket->id,
                    'crisis' => true,
                    'action_required' => true,
                ]),
                'created_at' => $ticket->created_at,
            ]);
        }

        // Notify admins
        foreach ($users['admins'] as $admin) {
            Notification::create([
                'user_id' => $admin->id,
                'type' => 'ticket',
                'title' => '🚨 Crisis Ticket Alert',
                'message' => "Crisis ticket #{$ticket->ticket_number} has been created and assigned for immediate intervention.",
                'priority' => 'high',
                'read' => false,
                'data' => json_encode([
                    'ticket_id' => $ticket->id,
                    'crisis' => true,
                ]),
                'created_at' => $ticket->created_at,
            ]);
        }
    }

    private function printSeedingSummary(): void
    {
        $totalTickets = Ticket::count();
        $openTickets = Ticket::where('status', Ticket::STATUS_OPEN)->count();
        $inProgressTickets = Ticket::where('status', Ticket::STATUS_IN_PROGRESS)->count();
        $resolvedTickets = Ticket::where('status', Ticket::STATUS_RESOLVED)->count();
        $closedTickets = Ticket::where('status', Ticket::STATUS_CLOSED)->count();
        $crisisTickets = Ticket::where('crisis_flag', true)->count();
        $unassignedTickets = Ticket::whereNull('assigned_to')->count();

        $this->command->info('');
        $this->command->info('📊 TICKET SEEDING SUMMARY:');
        $this->command->info("   Total Tickets: {$totalTickets}");
        $this->command->info("   📋 Open: {$openTickets}");
        $this->command->info("   🔄 In Progress: {$inProgressTickets}");
        $this->command->info("   ✅ Resolved: {$resolvedTickets}");
        $this->command->info("   🔒 Closed: {$closedTickets}");
        $this->command->info("   🚨 Crisis: {$crisisTickets}");
        $this->command->info("   ⏳ Unassigned: {$unassignedTickets}");
        $this->command->info('');
        $this->command->info('✨ Ready for testing role-based ticketing system!');
    }
}