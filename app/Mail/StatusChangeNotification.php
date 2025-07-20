<?php
// app/Mail/StatusChangeNotification.php - FIXED

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class StatusChangeNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $user;
    public $oldStatus;
    public $newStatus;
    public $adminUser;
    public $reason;

    public function __construct(User $user, string $oldStatus, string $newStatus, $adminUser = null, string $reason = '')
    {
        $this->user = $user;
        $this->oldStatus = $oldStatus;
        $this->newStatus = $newStatus;
        $this->adminUser = $adminUser;
        $this->reason = $reason;
        $this->onQueue('status-changes');
    }

    public function build()
    {
        $subject = 'Account Status Changed';
        
        if ($this->newStatus === 'active') {
            $subject = 'Account Activated';
        } elseif ($this->newStatus === 'inactive') {
            $subject = 'Account Deactivated';
        } elseif ($this->newStatus === 'suspended') {
            $subject = 'Account Suspended';
        }

        return $this->subject($subject . ' - ' . config('app.name'))
                    ->view('emails.status-change');
    }
}