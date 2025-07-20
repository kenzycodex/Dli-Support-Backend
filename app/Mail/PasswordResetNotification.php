<?php
// app/Mail/PasswordResetNotification.php - FIXED

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class PasswordResetNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $user;
    public $temporaryPassword;
    public $adminUser;
    public $resetReason;

    public function __construct(User $user, string $temporaryPassword, $adminUser = null, string $resetReason = 'Administrative password reset')
    {
        $this->user = $user;
        $this->temporaryPassword = $temporaryPassword;
        $this->adminUser = $adminUser;
        $this->resetReason = $resetReason;
        $this->onQueue('password-resets');
    }

    public function build()
    {
        return $this->subject('Password Reset - ' . config('app.name'))
                    ->view('emails.password-reset');
    }
}