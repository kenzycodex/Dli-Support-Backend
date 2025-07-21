<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\TicketAttachment;
use Illuminate\Support\Facades\Storage;

class DebugDownloads extends Command
{
    protected $signature = 'debug:downloads';
    protected $description = 'Debug download issues';

    public function handle()
    {
        $this->info('🔍 Debugging Download Issues...');
        
        // Check storage symlink
        $storageLinked = file_exists(public_path('storage'));
        $this->info('📁 Storage symlink exists: ' . ($storageLinked ? 'YES' : 'NO'));
        
        if (!$storageLinked) {
            $this->warn('⚠️ Storage symlink missing! Run: php artisan storage:link');
        }
        
        // Check sample attachments
        $this->info('📎 Checking sample attachments...');
        $attachments = TicketAttachment::with('ticket')->take(5)->get();
        
        if ($attachments->isEmpty()) {
            $this->warn('⚠️ No attachments found in database');
            return 0;
        }
        
        foreach ($attachments as $attachment) {
            $this->info("\n📄 Attachment ID: {$attachment->id}");
            $this->info("   Original: {$attachment->original_name}");
            $this->info("   Stored path: {$attachment->file_path}");
            $this->info("   Size: {$attachment->file_size} bytes");
            
            // Check different path possibilities
            $paths = [
                'Public Storage' => storage_path('app/public/' . $attachment->file_path),
                'Direct Storage' => storage_path($attachment->file_path),
                'Public Path' => public_path('storage/' . $attachment->file_path),
            ];
            
            $found = false;
            foreach ($paths as $type => $path) {
                $exists = file_exists($path);
                $readable = $exists && is_readable($path);
                $size = $exists ? filesize($path) : 0;
                
                $status = $exists ? ($readable ? '✅ EXISTS & READABLE' : '⚠️ EXISTS BUT NOT READABLE') : '❌ NOT FOUND';
                $this->info("   {$type}: {$status}");
                $this->info("     Path: {$path}");
                
                if ($exists) {
                    $this->info("     Size: {$size} bytes");
                    $found = true;
                }
            }
            
            if (!$found) {
                $this->error("   ❌ FILE NOT FOUND IN ANY LOCATION!");
            }
        }
        
        // Check storage configuration
        $this->info("\n🔧 Storage Configuration:");
        $this->info('   Public disk root: ' . config('filesystems.disks.public.root'));
        $this->info('   Public disk URL: ' . config('filesystems.disks.public.url'));
        $this->info('   Default disk: ' . config('filesystems.default'));
        
        // Check permissions
        $this->info("\n🔐 Directory Permissions:");
        $dirs = [
            'storage/app' => storage_path('app'),
            'storage/app/public' => storage_path('app/public'),
            'public/storage' => public_path('storage'),
        ];
        
        foreach ($dirs as $name => $path) {
            if (file_exists($path)) {
                $perms = substr(sprintf('%o', fileperms($path)), -4);
                $this->info("   {$name}: {$perms}");
            } else {
                $this->warn("   {$name}: NOT FOUND");
            }
        }
        
        // Test storage write/read
        $this->info("\n🧪 Testing Storage Operations:");
        try {
            $testContent = 'Test content for download debugging - ' . now();
            $testFile = 'test-download-debug-' . time() . '.txt';
            
            // Write test file
            Storage::disk('public')->put($testFile, $testContent);
            $this->info("   ✅ Write test: SUCCESS");
            
            // Check if file exists via Laravel
            $existsViaLaravel = Storage::disk('public')->exists($testFile);
            $this->info("   📄 Exists via Laravel: " . ($existsViaLaravel ? 'YES' : 'NO'));
            
            // Get file path and check physically
            $physicalPath = storage_path('app/public/' . $testFile);
            $existsPhysically = file_exists($physicalPath);
            $this->info("   📄 Exists physically: " . ($existsPhysically ? 'YES' : 'NO'));
            $this->info("   📍 Physical path: {$physicalPath}");
            
            // Check public URL access
            $publicUrl = asset('storage/' . $testFile);
            $this->info("   🌐 Public URL: {$publicUrl}");
            
            // Read content back
            if ($existsViaLaravel) {
                $readContent = Storage::disk('public')->get($testFile);
                $contentMatches = $readContent === $testContent;
                $this->info("   📖 Read test: " . ($contentMatches ? 'SUCCESS' : 'FAILED'));
            }
            
            // Clean up
            Storage::disk('public')->delete($testFile);
            $this->info("   🗑️ Cleanup: SUCCESS");
            
        } catch (\Exception $e) {
            $this->error("   ❌ Storage test failed: " . $e->getMessage());
        }
        
        // Check ticket-attachments directory
        $this->info("\n📁 Ticket Attachments Directory:");
        $attachmentsDir = storage_path('app/public/ticket-attachments');
        
        if (!file_exists($attachmentsDir)) {
            $this->warn("   ⚠️ Directory doesn't exist: {$attachmentsDir}");
            $this->info("   🔧 Creating directory...");
            
            try {
                mkdir($attachmentsDir, 0755, true);
                $this->info("   ✅ Directory created successfully");
            } catch (\Exception $e) {
                $this->error("   ❌ Failed to create directory: " . $e->getMessage());
            }
        } else {
            $this->info("   ✅ Directory exists: {$attachmentsDir}");
            $perms = substr(sprintf('%o', fileperms($attachmentsDir)), -4);
            $this->info("   🔐 Permissions: {$perms}");
            
            // Check if writable
            $writable = is_writable($attachmentsDir);
            $this->info("   ✍️ Writable: " . ($writable ? 'YES' : 'NO'));
        }
        
        // Provide recommendations
        $this->info("\n💡 Recommendations:");
        
        if (!$storageLinked) {
            $this->warn("   1. Run: php artisan storage:link");
        }
        
        $this->info("   2. Ensure proper permissions:");
        $this->info("      chmod -R 755 storage/");
        $this->info("      chmod -R 755 public/storage/");
        
        $this->info("   3. Check your .env file:");
        $this->info("      FILESYSTEM_DISK=public");
        
        $this->info("   4. If files are missing, they may need re-upload");
        
        $this->info("\n🏁 Debug complete!");
        
        return 0;
    }
}

// To use this command:
// 1. Create the command: php artisan make:command DebugDownloads
// 2. Replace the generated content with this code
// 3. Run: php artisan debug:downloads