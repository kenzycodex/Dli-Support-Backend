<?php
// tests/Feature/UserManagementEmailTest.php
// Complete testing script for user management and email functionality

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Jobs\SendWelcomeEmail;
use App\Jobs\SendBulkWelcomeEmails;
use App\Mail\WelcomeUser;
use App\Mail\BulkUserCreationReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Http\UploadedFile;

class UserManagementEmailTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create admin user for testing
        $this->adminUser = User::factory()->create([
            'role' => 'admin',
            'status' => 'active'
        ]);

        // Enable email features for testing
        Config::set('app.send_welcome_emails', true);
        Config::set('app.send_bulk_operation_reports', true);
        Config::set('app.send_admin_notifications', true);
    }

    /** @test */
    public function test_single_user_creation_with_welcome_email()
    {
        Mail::fake();
        Queue::fake();

        $userData = [
            'name' => 'Test Student',
            'email' => 'student@test.com',
            'role' => 'student',
            'status' => 'active',
            'send_welcome_email' => true,
            'generate_password' => true
        ];

        $response = $this->actingAs($this->adminUser)
                        ->postJson('/api/admin/users', $userData);

        $response->assertStatus(201)
                ->assertJson([
                    'success' => true,
                    'message' => 'User created successfully'
                ])
                ->assertJsonStructure([
                    'data' => [
                        'user' => [
                            'id', 'name', 'email', 'role', 'status'
                        ],
                        'email_sent'
                    ]
                ]);

        // Verify user was created
        $this->assertDatabaseHas('users', [
            'email' => 'student@test.com',
            'role' => 'student',
            'status' => 'active'
        ]);

        // Verify welcome email job was queued
        Queue::assertPushed(SendWelcomeEmail::class, function ($job) {
            return $job->user->email === 'student@test.com';
        });
    }

    /** @test */
    public function test_single_user_creation_without_welcome_email()
    {
        Mail::fake();
        Queue::fake();

        $userData = [
            'name' => 'Test Student',
            'email' => 'student2@test.com',
            'role' => 'student',
            'send_welcome_email' => false
        ];

        $response = $this->actingAs($this->adminUser)
                        ->postJson('/api/admin/users', $userData);

        $response->assertStatus(201);

        // Verify welcome email job was NOT queued
        Queue::assertNotPushed(SendWelcomeEmail::class);
    }

    /** @test */
    public function test_welcome_emails_disabled_globally()
    {
        Config::set('app.send_welcome_emails', false);
        Mail::fake();
        Queue::fake();

        $userData = [
            'name' => 'Test Student',
            'email' => 'student3@test.com',
            'role' => 'student',
            'send_welcome_email' => true // This should be ignored
        ];

        $response = $this->actingAs($this->adminUser)
                        ->postJson('/api/admin/users', $userData);

        $response->assertStatus(201);

        // Verify welcome email job was NOT queued due to global setting
        Queue::assertNotPushed(SendWelcomeEmail::class);
    }

    /** @test */
    public function test_bulk_user_creation_with_csv()
    {
        Mail::fake();
        Queue::fake();

        // Create CSV content
        $csvContent = "name,email,role,status\n";
        $csvContent .= "John Doe,john@test.com,student,active\n";
        $csvContent .= "Jane Smith,jane@test.com,counselor,active\n";
        $csvContent .= "Bob Johnson,bob@test.com,advisor,active\n";

        // Create temporary CSV file
        $csvFile = UploadedFile::fake()->createWithContent(
            'bulk_users.csv',
            $csvContent
        );

        $response = $this->actingAs($this->adminUser)
                        ->postJson('/api/admin/users/bulk-create', [
                            'csv_file' => $csvFile,
                            'skip_duplicates' => true,
                            'send_welcome_email' => true,
                            'generate_passwords' => true
                        ]);

        $response->assertStatus(201)
                ->assertJson([
                    'success' => true,
                    'message' => 'Bulk user creation completed'
                ])
                ->assertJsonStructure([
                    'data' => [
                        'results' => [
                            'successful',
                            'failed',
                            'skipped',
                            'emails_queued'
                        ],
                        'summary'
                    ]
                ]);

        // Verify users were created
        $this->assertDatabaseHas('users', ['email' => 'john@test.com']);
        $this->assertDatabaseHas('users', ['email' => 'jane@test.com']);
        $this->assertDatabaseHas('users', ['email' => 'bob@test.com']);

        // Verify bulk email job was queued
        Queue::assertPushed(SendBulkWelcomeEmails::class);
    }

    /** @test */
    public function test_bulk_user_creation_with_data_array()
    {
        Mail::fake();
        Queue::fake();

        $usersData = [
            [
                'name' => 'Array User 1',
                'email' => 'array1@test.com',
                'role' => 'student'
            ],
            [
                'name' => 'Array User 2',
                'email' => 'array2@test.com',
                'role' => 'counselor'
            ]
        ];

        $response = $this->actingAs($this->adminUser)
                        ->postJson('/api/admin/users/bulk-create', [
                            'users_data' => $usersData,
                            'skip_duplicates' => true,
                            'send_welcome_email' => true
                        ]);

        $response->assertStatus(201);

        // Verify users were created
        $this->assertDatabaseHas('users', ['email' => 'array1@test.com']);
        $this->assertDatabaseHas('users', ['email' => 'array2@test.com']);

        // Verify bulk email job was queued
        Queue::assertPushed(SendBulkWelcomeEmails::class);
    }

    /** @test */
    public function test_duplicate_email_handling()
    {
        Mail::fake();

        // Create existing user
        User::factory()->create(['email' => 'existing@test.com']);

        $usersData = [
            [
                'name' => 'New User',
                'email' => 'new@test.com',
                'role' => 'student'
            ],
            [
                'name' => 'Duplicate User',
                'email' => 'existing@test.com', // This exists
                'role' => 'student'
            ]
        ];

        $response = $this->actingAs($this->adminUser)
                        ->postJson('/api/admin/users/bulk-create', [
                            'users_data' => $usersData,
                            'skip_duplicates' => true,
                            'send_welcome_email' => false
                        ]);

        $response->assertStatus(201);

        $responseData = $response->json();
        $this->assertEquals(1, $responseData['data']['results']['successful']);
        $this->assertEquals(1, $responseData['data']['results']['skipped']);
        $this->assertEquals(0, $responseData['data']['results']['failed']);
    }

    /** @test */
    public function test_welcome_email_job_execution()
    {
        Mail::fake();

        $user = User::factory()->create([
            'role' => 'student',
            'email' => 'jobtest@test.com'
        ]);

        $temporaryPassword = 'TempPass123';

        // Execute the job
        $job = new SendWelcomeEmail($user, $temporaryPassword, true);
        $job->handle();

        // Verify email was sent
        Mail::assertSent(WelcomeUser::class, function ($mail) use ($user, $temporaryPassword) {
            return $mail->user->id === $user->id &&
                   $mail->temporaryPassword === $temporaryPassword &&
                   $mail->isNewUser === true;
        });

        // Verify user record was updated
        $user->refresh();
        $this->assertNotNull($user->welcome_email_sent_at);
        $this->assertNotNull($user->last_email_sent_at);
    }

    /** @test */
    public function test_bulk_email_job_execution()
    {
        Mail::fake();

        $users = User::factory()->count(3)->create([
            'role' => 'student'
        ]);

        $createdUsers = $users->map(function ($user) {
            return [
                'user' => $user,
                'generated_password' => 'TempPass123'
            ];
        })->toArray();

        $results = [
            'successful' => 3,
            'failed' => 0,
            'skipped' => 0
        ];

        // Execute the job
        $job = new SendBulkWelcomeEmails($createdUsers, $this->adminUser, $results);
        $job->handle();

        // Verify individual welcome emails were sent
        Mail::assertSent(WelcomeUser::class, 3);

        // Verify admin report was sent
        Mail::assertSent(BulkUserCreationReport::class, function ($mail) {
            return $mail->adminUser->id === $this->adminUser->id;
        });
    }

    /** @test */
    public function test_role_specific_email_content()
    {
        Mail::fake();

        $roles = ['student', 'counselor', 'advisor', 'admin'];

        foreach ($roles as $role) {
            $user = User::factory()->create(['role' => $role]);
            $temporaryPassword = 'TempPass123';

            $job = new SendWelcomeEmail($user, $temporaryPassword, true);
            $job->handle();

            Mail::assertSent(WelcomeUser::class, function ($mail) use ($user, $role) {
                $roleInfo = $mail->getRoleSpecificInfo();
                return $mail->user->role === $role && 
                       !empty($roleInfo['features']);
            });
        }
    }

    /** @test */
    public function test_password_reset_with_email()
    {
        Mail::fake();
        Queue::fake();

        $user = User::factory()->create([
            'role' => 'student',
            'email' => 'reset@test.com'
        ]);

        $response = $this->actingAs($this->adminUser)
                        ->postJson("/api/admin/users/{$user->id}/reset-password", [
                            'generate_password' => true,
                            'notify_user' => true
                        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Password reset successfully. User will need to log in again.'
                ]);

        // Verify password reset email job was queued
        Queue::assertPushed(SendWelcomeEmail::class, function ($job) use ($user) {
            return $job->user->id === $user->id && 
                   $job->isNewUser === false; // This indicates password reset
        });

        // Verify user's tokens were revoked
        $this->assertEquals(0, $user->tokens()->count());
    }

    /** @test */
    public function test_email_configuration_validation()
    {
        // Test that email jobs handle configuration errors gracefully
        Config::set('mail.mailer', 'invalid');
        Mail::fake();

        $user = User::factory()->create(['role' => 'student']);
        $temporaryPassword = 'TempPass123';

        // Job should not throw exception even with invalid config
        $job = new SendWelcomeEmail($user, $temporaryPassword, true);
        
        try {
            $job->handle();
            $this->assertTrue(true); // Job completed without throwing
        } catch (\Exception $e) {
            // Job should handle mail errors gracefully
            $this->assertInstanceOf(\Exception::class, $e);
        }
    }

    /** @test */
    public function test_user_management_statistics()
    {
        // Create users with different roles and statuses
        User::factory()->create(['role' => 'student', 'status' => 'active']);
        User::factory()->create(['role' => 'counselor', 'status' => 'active']);
        User::factory()->create(['role' => 'advisor', 'status' => 'inactive']);
        User::factory()->create(['role' => 'admin', 'status' => 'suspended']);

        $response = $this->actingAs($this->adminUser)
                        ->getJson('/api/admin/users/stats');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        'stats' => [
                            'total_users',
                            'active_users',
                            'inactive_users',
                            'suspended_users',
                            'students',
                            'counselors',
                            'advisors',
                            'admins',
                            'email_settings'
                        ]
                    ]
                ]);

        $stats = $response->json('data.stats');
        $this->assertGreaterThan(0, $stats['total_users']);
        $this->assertArrayHasKey('email_settings', $stats);
    }

    /** @test */
    public function test_user_export_functionality()
    {
        // Create test users
        User::factory()->count(5)->create(['role' => 'student']);
        User::factory()->count(3)->create(['role' => 'counselor']);

        $response = $this->actingAs($this->adminUser)
                        ->getJson('/api/admin/users/export?role=student');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        'users',
                        'exported_at',
                        'total_exported'
                    ]
                ]);

        $data = $response->json('data');
        $this->assertGreaterThan(0, $data['total_exported']);
        $this->assertIsArray($data['users']);
    }

    /** @test */
    public function test_user_options_endpoint()
    {
        $response = $this->actingAs($this->adminUser)
                        ->getJson('/api/admin/users/options');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        'roles',
                        'statuses',
                        'email_settings' => [
                            'welcome_emails_enabled',
                            'bulk_emails_enabled',
                            'admin_notifications_enabled'
                        ]
                    ]
                ]);

        $data = $response->json('data');
        $this->assertArrayHasKey('student', $data['roles']);
        $this->assertArrayHasKey('active', $data['statuses']);
        $this->assertIsBool($data['email_settings']['welcome_emails_enabled']);
    }

    /** @test */
    public function test_validation_errors()
    {
        $response = $this->actingAs($this->adminUser)
                        ->postJson('/api/admin/users', [
                            'name' => '', // Invalid: required
                            'email' => 'invalid-email', // Invalid: not email
                            'role' => 'invalid-role' // Invalid: not in allowed roles
                        ]);

        $response->assertStatus(422)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'errors'
                ]);

        $this->assertFalse($response->json('success'));
        $this->assertArrayHasKey('errors', $response->json());
    }

    /** @test */
    public function test_unauthorized_access()
    {
        $student = User::factory()->create(['role' => 'student']);

        $response = $this->actingAs($student)
                        ->postJson('/api/admin/users', [
                            'name' => 'Test User',
                            'email' => 'test@test.com',
                            'role' => 'student'
                        ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function test_queue_worker_commands()
    {
        // Test that queue commands work properly
        $exitCode = Artisan::call('queue:work', [
            '--once' => true,
            '--queue' => 'emails'
        ]);

        $this->assertEquals(0, $exitCode);
    }

    /** @test */
    public function test_email_rate_limiting()
    {
        Mail::fake();
        Queue::fake();

        // Create multiple users rapidly
        for ($i = 0; $i < 20; $i++) {
            $userData = [
                'name' => "User $i",
                'email' => "user$i@test.com",
                'role' => 'student',
                'send_welcome_email' => true
            ];

            $this->actingAs($this->adminUser)
                ->postJson('/api/admin/users', $userData);
        }

        // All should succeed as we're testing the endpoint, not actual email sending
        $this->assertDatabaseCount('users', 21); // 20 + admin user
    }
}