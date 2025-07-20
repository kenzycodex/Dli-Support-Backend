<?php
// app/Mail/WelcomeUser.php - CRITICAL FIX FOR CLOSURE SERIALIZATION

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class WelcomeUser extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $user;
    public $temporaryPassword;
    public $isNewUser;

    public function __construct(User $user, string $temporaryPassword, bool $isNewUser = true)
    {
        $this->user = $user;
        $this->temporaryPassword = $temporaryPassword;
        $this->isNewUser = $isNewUser;
        
        // Set queue
        $this->onQueue('emails');
    }

    public function build()
    {
        $subject = $this->isNewUser 
            ? 'Welcome to ' . config('app.name') . ' - Your Account is Ready!'
            : 'Password Reset - ' . config('app.name');

        return $this->subject($subject)
                    ->view('emails.welcome-user');
    }

    // CRITICAL FIX: Remove ALL methods that might contain closures
    // No attachments(), no complex helper methods that could cause serialization issues
}