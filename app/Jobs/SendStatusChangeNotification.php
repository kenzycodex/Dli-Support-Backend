<?php
// app/Jobs/SendStatusChangeNotification.php - FIXED

namespace App\Jobs;

use App\Models\User;
use App\Mail\StatusChangeNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Exception;

class SendStatusChangeNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $user;
    public $oldStatus;
    public $newStatus;
    public $adminUser;
    public $reason;
    public $tries = 3;
    public $timeout = 60;

    public function __construct(User $user, string $oldStatus, string $newStatus, $adminUser = null, string $reason = '')
    {
        $this->user = $user;
        $this->oldStatus = $oldStatus;
        $this->newStatus = $newStatus;
        $this->adminUser = $adminUser;
        $this->reason = $reason;
        $this->onQueue('status-changes');
    }

    public function handle()
    {
        try {
            Log::info('Starting status change email job', [
                'user_id' => $this->user->id,
                'email' => $this->user->email,
                'old_status' => $this->oldStatus,
                'new_status' => $this->newStatus,
                'admin_user_id' => $this->adminUser?->id
            ]);

            if (!config('app.send_email_on_status_change', true)) {
                Log::info('Status change emails disabled globally, skipping');
                return;
            }

            $user = User::find($this->user->id);
            if (!$user) {
                Log::warning('User no longer exists, skipping status change email');
                return;
            }

            // Skip if status hasn't actually changed
            if ($this->oldStatus === $this->newStatus) {
                Log::info('Status unchanged, skipping email');
                return;
            }

            $statusEmail = new StatusChangeNotification(
                $user,
                $this->oldStatus,
                $this->newStatus,
                $this->adminUser,
                $this->reason
            );
            
            Mail::to($user->email)->send($statusEmail);

            // Update user tracking safely
            try {
                $user->update([
                    'last_email_sent_at' => now(),
                ]);
            } catch (Exception $e) {
                Log::warning('Failed to update email tracking: ' . $e->getMessage());
            }

            Log::info('Status change email sent successfully', [
                'user_id' => $user->id,
                'email' => $user->email,
                'old_status' => $this->oldStatus,
                'new_status' => $this->newStatus
            ]);

        } catch (Exception $e) {
            Log::error('Failed to send status change email', [
                'user_id' => $this->user->id,
                'email' => $this->user->email,
                'old_status' => $this->oldStatus,
                'new_status' => $this->newStatus,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function failed(Exception $exception)
    {
        Log::error('Status change email job failed permanently', [
            'user_id' => $this->user->id,
            'email' => $this->user->email,
            'old_status' => $this->oldStatus,
            'new_status' => $this->newStatus,
            'error' => $exception->getMessage()
        ]);
    }
}