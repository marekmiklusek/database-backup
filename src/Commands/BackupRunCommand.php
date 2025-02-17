<?php

namespace MarekMiklusek\DatabaseBackup\Commands;

use Exception;
use Throwable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;
use MarekMiklusek\DatabaseBackup\Enums\Driver;
use MarekMiklusek\DatabaseBackup\Events\BackupFailed;
use MarekMiklusek\DatabaseBackup\Events\BackupCreated;
use MarekMiklusek\DatabaseBackup\Services\ConfigService;
use MarekMiklusek\DatabaseBackup\Services\GoogleService;
use MarekMiklusek\DatabaseBackup\Commands\BackupCleanupCommand;

class BackupRunCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db-backup:run';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a backup of the database (using --defaults-extra-file) and store it on the specified disk.';

    /**
     * Execute the console command.
     */
    public function handle(ConfigService $service): int
    {
        $this->info('Starting database backup...');

        try {
            $driver = $service->getDriver();
            $localDirectory = $service->localDirectory();

            $date = now()->format($service->storage('filename.date_format'));
            $filename = $service->storage('filename.prefix') . "_{$date}.sql";

            File::ensureDirectoryExists($localDirectory);
            $localBackupPath = "{$localDirectory}/{$filename}";

            $credentialsFile = storage_path('app/tmp_mysql_credentials.cnf');

            $credentialsContent = <<<CNF
                                    [client]
                                    user={$service->mysqlDB('username')}
                                    password={$service->mysqlDB('password')}
                                    host={$service->mysqlDB('host')}
                                    port={$service->mysqlDB('port')}
                                    CNF;

            File::put($credentialsFile, $credentialsContent);

            $command = sprintf(
                'mysqldump --defaults-extra-file="%s" %s > "%s"',
                $credentialsFile,
                $service->mysqlDB('database'),
                $localBackupPath,
            );

            $output = [];
            $resultCode = 0;
            exec($command, $output, $resultCode);

            File::delete($credentialsFile);

            if ($resultCode !== 0) {
                throw new Exception(
                    "Database backup failed (mysqldump exit code: {$resultCode}, mysqldump output: {$output})"
                );
            }

            if ($service->storage('use_both_disks')) {
                $this->storeBackupToGoogle($localDirectory, $localBackupPath, true);
            } else {
                match ($driver) {
                    Driver::LOCAL->value => $this->info('Backup stored to disk: [' . Driver::LOCAL->value . ']'),
                    Driver::GOOGLE->value => $this->storeBackupToGoogle($localDirectory, $localBackupPath),
                    default => throw new Exception("Invalid driver: [{$driver}]"),
                };
            }

            BackupCreated::dispatch();

            $this->backupCleanup($service->cleanup('automatic'));

            $this->line("\033[42m SUCCESS \033[0m Backup complete!");
            return Command::SUCCESS;
        } catch (Throwable $throwable) {
            BackupFailed::dispatch($throwable->getMessage());
            $this->line("\033[41;97m ERROR \033[0m " . $throwable->getMessage());
            return Command::FAILURE;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Private functions
    |--------------------------------------------------------------------------
    */

    private function storeBackupToGoogle(string $localDirectory, string $localBackupPath, bool $useBothDisks = false): void
    {
        (new GoogleService())->uploadBackup($localBackupPath);

        if ($useBothDisks) {
            $this->info('Backup stored on both disks: [' . Driver::LOCAL->value . '] and [' . Driver::GOOGLE->value . ']');
            return;
        }

        File::delete($localBackupPath);

        if (count(File::files($localDirectory)) === 0) {
            File::deleteDirectory($localDirectory);
        }

        $this->info('Backup stored on disk: [' . Driver::GOOGLE->value . ']');
    }

    private function backupCleanup(bool $automatic): void
    {
        if ($automatic) {
            $this->info('Starting automatic cleanup...');

            $exitCode = Artisan::call(BackupCleanupCommand::class);
            $output = Artisan::output();
            if ($exitCode !== 0) {
                throw new Exception("Automatic cleanup failed: {$output}");
            }

            $this->info($output);
        }
    }
}
