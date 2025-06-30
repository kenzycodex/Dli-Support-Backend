<?php
// database/seeders/UserSeeder.php (Fixed to handle existing users)

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('👥 Creating test users...');
        
        // Create or update users (use updateOrCreate to avoid duplicates)
        
        // Admin User
        $admin = User::updateOrCreate(
            ['email' => 'admin@schoolsupport.com'],
            [
                'name' => 'System Administrator',
                'password' => Hash::make('admin123'),
                'role' => 'admin',
                'status' => 'active',
                'employee_id' => 'EMP001',
                'email_verified_at' => now(),
            ]
        );

        // Demo Admin
        $demoAdmin = User::updateOrCreate(
            ['email' => 'admin@university.edu'],
            [
                'name' => 'Demo Admin',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'status' => 'active',
                'employee_id' => 'ADMIN001',
                'email_verified_at' => now(),
            ]
        );

        // Counselors
        $counselor1 = User::updateOrCreate(
            ['email' => 'counselor@university.edu'],
            [
                'name' => 'Dr. Sarah Wilson',
                'password' => Hash::make('password'),
                'role' => 'counselor',
                'status' => 'active',
                'employee_id' => 'CNS001',
                'specializations' => ['mental-health', 'crisis-support', 'stress-management'],
                'bio' => 'Licensed counselor specializing in student mental health and crisis intervention.',
                'email_verified_at' => now(),
            ]
        );

        $counselor2 = User::updateOrCreate(
            ['email' => 'emily.rodriguez@university.edu'],
            [
                'name' => 'Dr. Emily Rodriguez',
                'password' => Hash::make('password'),
                'role' => 'counselor',
                'status' => 'active',
                'employee_id' => 'CNS002',
                'specializations' => ['anxiety', 'depression', 'academic-stress'],
                'bio' => 'Clinical psychologist with expertise in anxiety and depression counseling.',
                'email_verified_at' => now(),
            ]
        );

        // Advisors
        $advisor1 = User::updateOrCreate(
            ['email' => 'advisor@university.edu'],
            [
                'name' => 'Prof. Michael Chen',
                'password' => Hash::make('password'),
                'role' => 'advisor',
                'status' => 'active',
                'employee_id' => 'ADV001',
                'specializations' => ['academic-planning', 'career-guidance', 'course-selection'],
                'bio' => 'Academic advisor with 10+ years of experience in student support services.',
                'email_verified_at' => now(),
            ]
        );

        $advisor2 = User::updateOrCreate(
            ['email' => 'james.park@university.edu'],
            [
                'name' => 'Dr. James Park',
                'password' => Hash::make('password'),
                'role' => 'advisor',
                'status' => 'active',
                'employee_id' => 'ADV002',
                'specializations' => ['graduate-programs', 'research-opportunities', 'internships'],
                'bio' => 'Specialized in graduate program advising and research mentorship.',
                'email_verified_at' => now(),
            ]
        );

        // Students
        $student1 = User::updateOrCreate(
            ['email' => 'student@university.edu'],
            [
                'name' => 'Demo Student',
                'password' => Hash::make('password'),
                'role' => 'student',
                'status' => 'active',
                'student_id' => 'STU001',
                'date_of_birth' => '2002-05-15',
                'email_verified_at' => now(),
            ]
        );

        $student2 = User::updateOrCreate(
            ['email' => 'john.smith@university.edu'],
            [
                'name' => 'John Smith',
                'password' => Hash::make('password'),
                'role' => 'student',
                'status' => 'active',
                'student_id' => 'STU002',
                'date_of_birth' => '2001-09-22',
                'email_verified_at' => now(),
            ]
        );

        $student3 = User::updateOrCreate(
            ['email' => 'emma.rodriguez@university.edu'],
            [
                'name' => 'Emma Rodriguez',
                'password' => Hash::make('password'),
                'role' => 'student',
                'status' => 'active',
                'student_id' => 'STU003',
                'date_of_birth' => '2003-01-10',
                'email_verified_at' => now(),
            ]
        );

        $student4 = User::updateOrCreate(
            ['email' => 'david.johnson@university.edu'],
            [
                'name' => 'David Johnson',
                'password' => Hash::make('password'),
                'role' => 'student',
                'status' => 'active',
                'student_id' => 'STU004',
                'date_of_birth' => '2002-11-03',
                'email_verified_at' => now(),
            ]
        );

        $student5 = User::updateOrCreate(
            ['email' => 'lisa.wang@university.edu'],
            [
                'name' => 'Lisa Wang',
                'password' => Hash::make('password'),
                'role' => 'student',
                'status' => 'active',
                'student_id' => 'STU005',
                'date_of_birth' => '2001-07-18',
                'email_verified_at' => now(),
            ]
        );

        $student6 = User::updateOrCreate(
            ['email' => 'alex.thompson@university.edu'],
            [
                'name' => 'Alex Thompson',
                'password' => Hash::make('password'),
                'role' => 'student',
                'status' => 'active',
                'student_id' => 'STU006',
                'date_of_birth' => '2002-03-25',
                'email_verified_at' => now(),
            ]
        );

        // Additional staff for better testing
        $counselor3 = User::updateOrCreate(
            ['email' => 'maria.garcia@university.edu'],
            [
                'name' => 'Dr. Maria Garcia',
                'password' => Hash::make('password'),
                'role' => 'counselor',
                'status' => 'active',
                'employee_id' => 'CNS003',
                'specializations' => ['trauma', 'grief-counseling', 'crisis-intervention'],
                'bio' => 'Trauma specialist with experience in crisis intervention and grief counseling.',
                'email_verified_at' => now(),
            ]
        );

        $this->command->info('✅ Users seeded successfully!');
        $this->command->info('');
        $this->command->info('🔐 Login Credentials:');
        $this->command->info('   👑 Admin: admin@schoolsupport.com / admin123');
        $this->command->info('   👑 Demo Admin: admin@university.edu / password');
        $this->command->info('   👩‍⚕️ Counselor: counselor@university.edu / password');
        $this->command->info('   👨‍🏫 Advisor: advisor@university.edu / password');
        $this->command->info('   👨‍🎓 Student: student@university.edu / password');
        $this->command->info('   📧 All demo users: password');
    }
}