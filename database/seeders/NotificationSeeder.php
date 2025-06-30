<?php
// database/seeders/NotificationSeeder.php 

namespace Database\Seeders;

use App\Models\User;
use App\Models\Notification;
use Illuminate\Database\Seeder;

class NotificationSeeder extends Seeder
{
    public function run(): void
    {
        // Get existing users from your seeder
        $student = User::where('email', 'student@university.edu')->first();
        $adaStudent = User::where('email', 'ada.balogun@university.edu')->first() ?? $student;
        $counselor = User::where('email', 'counselor@university.edu')->first();
        $advisor = User::where('email', 'advisor@university.edu')->first();
        $admin = User::where('email', 'admin@schoolsupport.com')->first();
        
        // Additional students
        $johnSmith = User::where('email', 'john.smith@university.edu')->first();
        $emmaRodriguez = User::where('email', 'emma.rodriguez@university.edu')->first();

        // Create sample notifications for students
        if ($student) {
            Notification::create([
                'user_id' => $student->id,
                'type' => 'appointment',
                'title' => 'Appointment Reminder',
                'message' => 'Your counseling session with Dr. Sarah Wilson is tomorrow at 2:00 PM',
                'priority' => 'high',
                'read' => false,
                'data' => ['appointment_id' => 1, 'counselor' => 'Dr. Sarah Wilson'],
            ]);

            Notification::create([
                'user_id' => $student->id,
                'type' => 'ticket',
                'title' => 'Support Ticket Update',
                'message' => 'Your ticket #T001 has been resolved',
                'priority' => 'medium',
                'read' => false,
                'data' => ['ticket_id' => 1, 'ticket_number' => 'T001'],
            ]);

            Notification::create([
                'user_id' => $student->id,
                'type' => 'system',
                'title' => 'System Maintenance',
                'message' => 'The system will be under maintenance on Sunday from 2 AM to 4 AM',
                'priority' => 'low',
                'read' => true,
                'read_at' => now()->subHours(2),
            ]);

            Notification::create([
                'user_id' => $student->id,
                'type' => 'reminder',
                'title' => 'Wellness Check',
                'message' => 'How are you feeling today? Take a quick mood assessment',
                'priority' => 'low',
                'read' => true,
                'read_at' => now()->subDays(1),
            ]);
        }

        // Create notifications for other students
        if ($johnSmith) {
            Notification::create([
                'user_id' => $johnSmith->id,
                'type' => 'appointment',
                'title' => 'Appointment Confirmed',
                'message' => 'Your appointment with Prof. Michael Chen has been confirmed for Friday at 10:00 AM',
                'priority' => 'medium',
                'read' => false,
                'data' => ['appointment_id' => 2, 'advisor' => 'Prof. Michael Chen'],
            ]);
        }

        if ($emmaRodriguez) {
            Notification::create([
                'user_id' => $emmaRodriguez->id,
                'type' => 'ticket',
                'title' => 'New Response',
                'message' => 'You have a new response on your support ticket #T002',
                'priority' => 'medium',
                'read' => false,
                'data' => ['ticket_id' => 2, 'ticket_number' => 'T002'],
            ]);
        }

        // Create notifications for counselor
        if ($counselor) {
            Notification::create([
                'user_id' => $counselor->id,
                'type' => 'ticket',
                'title' => 'New Support Ticket',
                'message' => 'A new high-priority ticket has been assigned to you from Ada Balogun',
                'priority' => 'high',
                'read' => false,
                'data' => ['ticket_id' => 1, 'student' => 'Ada Balogun'],
            ]);

            Notification::create([
                'user_id' => $counselor->id,
                'type' => 'system',
                'title' => 'Crisis Alert',
                'message' => 'A student ticket contains crisis keywords and requires immediate attention',
                'priority' => 'high',
                'read' => false,
                'data' => ['ticket_id' => 3, 'crisis' => true],
            ]);

            Notification::create([
                'user_id' => $counselor->id,
                'type' => 'reminder',
                'title' => 'Daily Check-in',
                'message' => 'You have 3 pending tickets that need your attention',
                'priority' => 'medium',
                'read' => true,
                'read_at' => now()->subHours(4),
            ]);
        }

        // Create notifications for advisor
        if ($advisor) {
            Notification::create([
                'user_id' => $advisor->id,
                'type' => 'ticket',
                'title' => 'Student Response',
                'message' => 'John Smith responded to ticket #T004 regarding academic planning',
                'priority' => 'medium',
                'read' => false,
                'data' => ['ticket_id' => 4, 'student' => 'John Smith'],
            ]);
        }

        // Create notifications for admin
        if ($admin) {
            Notification::create([
                'user_id' => $admin->id,
                'type' => 'system',
                'title' => 'System Status',
                'message' => 'Daily system backup completed successfully',
                'priority' => 'low',
                'read' => true,
                'read_at' => now()->subMinutes(30),
            ]);

            Notification::create([
                'user_id' => $admin->id,
                'type' => 'ticket',
                'title' => 'Unassigned Tickets',
                'message' => 'There are 2 unassigned tickets that need staff assignment',
                'priority' => 'medium',
                'read' => false,
                'data' => ['unassigned_count' => 2],
            ]);
        }

        $this->command->info('Sample notifications created successfully!');
    }
}