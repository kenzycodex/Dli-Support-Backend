<?php
// app/Mail/BulkUserCreationReport.php - FIXED

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class BulkUserCreationReport extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $adminUser;
    public $results;
    public $createdUsers;
    public $operationType;

    public function __construct($adminUser, $results, $createdUsers, $operationType = 'bulk_creation')
    {
        $this->adminUser = $adminUser;
        $this->results = $results;
        $this->createdUsers = $createdUsers;
        $this->operationType = $operationType;
        $this->onQueue('admin-reports');
    }

    public function build()
    {
        return $this->subject('Bulk User Creation Report - ' . config('app.name'))
                    ->view('emails.bulk-creation-report');
    }
}