<?php
// app/Jobs/SendPasswordResetNotification.php - FIXED

namespace App\Jobs;

use App\Models\User;
use App\Mail\PasswordResetNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Exception;

class SendPasswordResetNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $user;
    public $temporaryPassword;
    public $adminUser;
    public $resetReason;
    public $tries = 3;
    public $timeout = 60;

    public function __construct(User $user, string $temporaryPassword, $adminUser = null, string $resetReason = 'Administrative password reset')
    {
        $this->user = $user;
        $this->temporaryPassword = $temporaryPassword;
        $this->adminUser = $adminUser;
        $this->resetReason = $resetReason;
        $this->onQueue('password-resets');
    }

    public function handle()
    {
        try {
            Log::info('Starting password reset email job', [
                'user_id' => $this->user->id,
                'email' => $this->user->email,
                'admin_user_id' => $this->adminUser?->id
            ]);

            if (!config('app.send_password_reset_emails', true)) {
                Log::info('Password reset emails disabled globally, skipping');
                return;
            }

            $user = User::find($this->user->id);
            if (!$user) {
                Log::warning('User no longer exists, skipping password reset email');
                return;
            }

            $resetEmail = new PasswordResetNotification(
                $user, 
                $this->temporaryPassword, 
                $this->adminUser, 
                $this->resetReason
            );
            
            Mail::to($user->email)->send($resetEmail);

            // Update user tracking safely
            try {
                $user->update([
                    'password_reset_email_sent_at' => now(),
                    'last_email_sent_at' => now(),
                ]);
            } catch (Exception $e) {
                Log::warning('Failed to update email tracking: ' . $e->getMessage());
            }

            Log::info('Password reset email sent successfully', [
                'user_id' => $user->id,
                'email' => $user->email
            ]);

        } catch (Exception $e) {
            Log::error('Failed to send password reset email', [
                'user_id' => $this->user->id,
                'email' => $this->user->email,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function failed(Exception $exception)
    {
        Log::error('Password reset email job failed permanently', [
            'user_id' => $this->user->id,
            'email' => $this->user->email,
            'error' => $exception->getMessage()
        ]);
    }
}