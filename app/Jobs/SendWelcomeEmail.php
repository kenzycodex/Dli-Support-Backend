<?php
// app/Jobs/SendWelcomeEmail.php - CRITICAL FIXES

namespace App\Jobs;

use App\Models\User;
use App\Mail\WelcomeUser;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema; // CRITICAL: Added missing import
use Exception;

class SendWelcomeEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $user;
    public $temporaryPassword;
    public $isNewUser;
    public $tries = 3;
    public $timeout = 60;

    public function __construct(User $user, string $temporaryPassword, bool $isNewUser = true)
    {
        $this->user = $user;
        $this->temporaryPassword = $temporaryPassword;
        $this->isNewUser = $isNewUser;
        
        $this->onQueue('emails');
    }

    public function handle()
    {
        try {
            Log::info('🚀 Starting welcome email job', [
                'user_id' => $this->user->id,
                'email' => $this->user->email,
                'is_new_user' => $this->isNewUser,
            ]);

            // Check if welcome emails are enabled
            if (!config('app.send_welcome_emails', true)) {
                Log::info('Welcome emails disabled, skipping');
                return;
            }

            // Check if user still exists
            $user = User::find($this->user->id);
            if (!$user) {
                Log::warning('User no longer exists, skipping email');
                return;
            }

            // Create and send email
            $welcomeEmail = new WelcomeUser($user, $this->temporaryPassword, $this->isNewUser);
            Mail::to($user->email)->send($welcomeEmail);

            Log::info('✅ Welcome email sent successfully', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            // Update user tracking safely
            try {
                $user->update([
                    'welcome_email_sent_at' => now(),
                    'last_email_sent_at' => now(),
                ]);
            } catch (Exception $e) {
                Log::warning('Failed to update email tracking: ' . $e->getMessage());
            }

        } catch (Exception $e) {
            Log::error('❌ Failed to send welcome email', [
                'user_id' => $this->user->id,
                'email' => $this->user->email,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(Exception $exception)
    {
        Log::error('🚨 Welcome email job failed permanently', [
            'user_id' => $this->user->id,
            'email' => $this->user->email,
            'error' => $exception->getMessage(),
        ]);
    }
}