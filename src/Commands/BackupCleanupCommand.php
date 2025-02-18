<?php

namespace MarekMiklusek\DatabaseBackup\Commands;

use Exception;
use Throwable;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use MarekMiklusek\DatabaseBackup\Enums\Driver;
use MarekMiklusek\DatabaseBackup\Services\ConfigService;
use MarekMiklusek\DatabaseBackup\Services\GoogleService;
use MarekMiklusek\DatabaseBackup\Notifications\CleanupFailedNotification;
use MarekMiklusek\DatabaseBackup\Notifications\CleanupSuccessNotification;

class BackupCleanupCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db-backup:cleanup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cleanup old backups based on the number of days specified in the config file.';

    /**
     * Execute the console command.
     */
    public function handle(ConfigService $service): int
    {
        $this->line('');
        $this->info('Starting database backup cleanup...');

        try {
            $driver = $service->getDriver();
            $daysToKeep = $service->cleanup('days_to_keep');

            if ($daysToKeep == 0) {
                $this->line('');
                $this->line("\033[30;43m WARN \033[0m \033[97mCleanup is disabled in the config file, 'days_to_keep' is set to \033[1;33m{$daysToKeep}\033[0m");
                return Command::SUCCESS;
            }

            $this->line("\033[32mCleaning up backups older than \033[1;33m{$daysToKeep}\033[0m \033[32mon disk:\033[0m \033[36m[{$driver}]\033[32m...\033[0m");

            if ($service->storage('use_both_disks')) {
                $this->cleanupLocalBackups($service->localDirectory(), $daysToKeep);
                $this->cleanupGoogleBackups($daysToKeep);
                $this->line("\033[42m SUCCESS \033[0m Cleanup complete for both disks!");
            } else {
                match ($driver) {
                    Driver::LOCAL->value => $this->cleanupLocalBackups($service->localDirectory(), $daysToKeep),
                    Driver::GOOGLE->value => $this->cleanupGoogleBackups($daysToKeep),
                    default => throw new Exception("Invalid driver: [{$driver}]"),
                };
    
                $this->line('');
                $this->line("\033[42m SUCCESS \033[0m Cleanup complete!");
            }

            if ($service->notifications('events.cleanup_successful')) {
                Notification::route('mail', $service->notifications('mail.to'))
                    ->notify(new CleanupSuccessNotification());
            }

            return Command::SUCCESS;

        } catch (Throwable $throwable) {
            if ($service->notifications('events.cleanup_failed')) {
                Notification::route('mail', $service->notifications('mail.to'))
                    ->notify(new CleanupFailedNotification($throwable->getMessage()));
            }
            
            $this->line("\033[41;97m ERROR \033[0m " . $throwable->getMessage());
            return Command::FAILURE;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Private functions
    |--------------------------------------------------------------------------
    */

    private function cleanupLocalBackups(string $localDirectory, int $daysToKeep): void
    {
        if (! File::exists($localDirectory)) {
            throw new Exception("Directory not found: {$localDirectory}");
        }

        $countDeleted = 0;
        $files = File::files($localDirectory);

        foreach ($files as $file) {
            $created = Carbon::createFromTimestamp($file->getCtime());
            $lastModified = Carbon::createFromTimestamp($file->getMTime());

            if ($created->diffInDays(now()) >= $daysToKeep) { 
                $countDeleted++;
                $this->line(sprintf(
                    "\033[32mDeleting old \033[36m[%s]\033[32m backup: \033[1;97m%s\033[0m (Created: \033[1;33m%s\033[0m, Last Modified: \033[1;33m%s\033[0m)", 
                    Driver::LOCAL->value, 
                    $file->getFilename(), 
                    $created->toDateTimeString(), 
                    $lastModified->toDateTimeString()
                ));
                
                File::delete($file->getPathname());
            }
        }

        if ($countDeleted === 0) {
            $this->line(''); 
            $this->line("\033[97;44m INFO \033[0m No old \033[36m[".Driver::LOCAL->value."]\033[0m \033[97mbackups found\033[0m");
        }

        if (count(File::files($localDirectory)) === 0) {
            File::deleteDirectory($localDirectory);
        }
    }

    private function cleanupGoogleBackups(int $daysToKeep): void
    {
        $countDeleted = 0;
        $googleService = new GoogleService();
        $files = $googleService->listBackups(); 

        foreach ($files as $file) {
            $created = Carbon::parse($file['createdTime']);
            $lastModified = Carbon::parse($file['modifiedTime']);

            if ($created->diffInDays(now()) >= $daysToKeep) {
                $countDeleted++;
                $this->line(sprintf(
                    "\033[32mDeleting old \033[36m[%s]\033[32m backup: \033[1;97m%s\033[0m (Created: \033[1;33m%s\033[0m, Last Modified: \033[1;33m%s\033[0m)", 
                    Driver::GOOGLE->value, 
                    $file['name'], 
                    $created->toDateTimeString(),
                    $lastModified->toDateTimeString()
                ));                

                $googleService->deleteFile($file['id']);
            }
        }

        if ($countDeleted === 0) {
            $this->line(''); 
            $this->line("\033[97;44m INFO \033[0m No old \033[36m[".Driver::GOOGLE->value."]\033[0m \033[97mbackups found\033[0m");
        }
    }
}
