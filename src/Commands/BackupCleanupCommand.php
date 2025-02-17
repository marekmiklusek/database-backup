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
        try {
            $driver = $service->getDriver();
            $daysToKeep = $service->cleanup('days_to_keep');

            if ($daysToKeep == 0) {
                $this->info("Cleanup is disabled in the config file, days to keep is set to {$daysToKeep}");
                return Command::SUCCESS;
            }

            $this->info("Cleaning up backups older than {$daysToKeep} days...");

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
                $this->info(sprintf(
                    'Deleting old %s backup: %s (Created: %s, Last Modified: %s)', 
                    Driver::LOCAL->value, 
                    $file->getFilename(), 
                    $created->toDateTimeString(), 
                    $lastModified->toDateTimeString(),
                ));

                File::delete($file->getPathname());
            }
        }

        if ($countDeleted === 0) {
            $this->info('No old '.Driver::LOCAL->value.' backups found');
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
                $this->info(sprintf(
                    'Deleting old %s backup: %s (Created: %s, Last Modified: %s)', 
                    Driver::GOOGLE->value, 
                    $file['name'], 
                    $created->toDateTimeString(),
                    $lastModified->toDateTimeString(),
                ));

                $googleService->deleteFile($file['id']);
            }
        }

        if ($countDeleted === 0) {
            $this->info('No old '.Driver::GOOGLE->value.' backups found');
        }
    }
}
