<?php
// database/seeders/DatabaseSeeder.php (Updated for role-based ticketing system)

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database for the role-based support system.
     */
    public function run(): void
    {
        $this->command->info('🌱 Starting database seeding for Student Support Platform...');
        
        // Seed in the correct order due to dependencies
        $this->call([
            UserSeeder::class,           // Create users first (students, counselors, advisors, admins)
            TicketSeeder::class,         // Create tickets with relationships to users
            NotificationSeeder::class,   // Create notifications for users
        ]);
        
        $this->command->info('');
        $this->command->info('🎉 Database seeding completed successfully!');
        $this->command->info('');
        $this->command->info('🔐 Test Login Credentials:');
        $this->command->info('   👨‍🎓 Student: student@university.edu / password');
        $this->command->info('   👩‍⚕️ Counselor: counselor@university.edu / password');
        $this->command->info('   👨‍🏫 Advisor: advisor@university.edu / password');
        $this->command->info('   👩‍💼 Admin: admin@university.edu / password');
        $this->command->info('');
        $this->command->info('🚀 Ready to test the role-based ticketing system!');
    }
}