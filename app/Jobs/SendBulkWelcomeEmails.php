<?php
// app/Jobs/SendBulkWelcomeEmails.php - FIXED

namespace App\Jobs;

use App\Models\User;
use App\Mail\WelcomeUser;
use App\Mail\BulkUserCreationReport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Exception;

class SendBulkWelcomeEmails implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $createdUsers;
    public $adminUser;
    public $results;
    public $tries = 3;
    public $timeout = 600;

    public function __construct($createdUsers, $adminUser, $results)
    {
        $this->createdUsers = $createdUsers;
        $this->adminUser = $adminUser;
        $this->results = $results;
        $this->onQueue('bulk-emails');
    }

    public function handle()
    {
        try {
            Log::info('Starting bulk welcome email sending', [
                'user_count' => count($this->createdUsers),
                'admin_id' => $this->adminUser?->id
            ]);

            if (!config('app.send_welcome_emails', true)) {
                Log::info('Welcome emails disabled globally, skipping');
                $this->sendAdminReport(0, 0);
                return;
            }

            $emailsSent = 0;
            $emailsFailed = 0;

            foreach ($this->createdUsers as $userData) {
                try {
                    $user = User::find($userData['user']->id);
                    if (!$user) {
                        Log::warning('User no longer exists, skipping', [
                            'user_id' => $userData['user']->id
                        ]);
                        $emailsFailed++;
                        continue;
                    }

                    $email = new WelcomeUser($user, $userData['generated_password']);
                    Mail::to($user->email)->send($email);
                    $emailsSent++;

                    // Update user tracking safely
                    try {
                        $user->update([
                            'welcome_email_sent_at' => now(),
                            'last_email_sent_at' => now(),
                        ]);
                    } catch (Exception $e) {
                        Log::warning('Failed to update email tracking: ' . $e->getMessage());
                    }

                    Log::info('Welcome email sent', [
                        'user_id' => $user->id,
                        'email' => $user->email
                    ]);

                    // Small delay between emails
                    if (count($this->createdUsers) > 10) {
                        usleep(500000); // 0.5 second delay
                    }

                } catch (Exception $e) {
                    Log::error('Failed to send welcome email', [
                        'user_id' => $userData['user']->id ?? 'unknown',
                        'error' => $e->getMessage()
                    ]);
                    $emailsFailed++;
                }
            }

            Log::info('Bulk welcome emails completed', [
                'emails_sent' => $emailsSent,
                'emails_failed' => $emailsFailed,
                'total_users' => count($this->createdUsers)
            ]);

            // Send admin report
            $this->sendAdminReport($emailsSent, $emailsFailed);

        } catch (Exception $e) {
            Log::error('Bulk welcome email job failed', [
                'error' => $e->getMessage(),
                'user_count' => count($this->createdUsers)
            ]);
            throw $e;
        }
    }

    private function sendAdminReport(int $emailsSent, int $emailsFailed): void
    {
        if (!$this->adminUser || !config('app.send_bulk_operation_reports', true)) {
            return;
        }

        try {
            $reportResults = array_merge($this->results, [
                'emails_sent' => $emailsSent,
                'emails_failed' => $emailsFailed,
            ]);

            $reportEmail = new BulkUserCreationReport(
                $this->adminUser, 
                $reportResults, 
                $this->createdUsers
            );
            
            Mail::to($this->adminUser->email)->send($reportEmail);

            Log::info('Admin report sent successfully', [
                'admin_id' => $this->adminUser->id
            ]);

        } catch (Exception $e) {
            Log::error('Failed to send admin report', [
                'admin_id' => $this->adminUser->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function failed(Exception $exception)
    {
        Log::error('Bulk welcome email job failed permanently', [
            'user_count' => count($this->createdUsers),
            'admin_id' => $this->adminUser?->id,
            'error' => $exception->getMessage()
        ]);
    }
}