<?php
namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;

class BackupService
{
    private const MAX_BACKUPS = 7; // Mantener últimos 7 backups

    private EntityManagerInterface $em;
    private string $backupPath;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
        $this->backupPath = $_ENV['BACKUP_PATH'] ?? dirname(__DIR__, 2) . '/backups';

        if (!is_dir($this->backupPath)) {
            logger()->info('Creating backup directory', ['path' => $this->backupPath]);
            mkdir($this->backupPath, 0755, true);
        }
    }

    public function createBackup(): bool
    {
        try {
            $filename = date('Y-m-d_His') . '_backup';
            logger()->info('Starting backup process', ['filename' => $filename]);

            // Backup de la base de datos
            logger()->debug('Starting database backup');
            $this->backupDatabase($filename);

            // Backup de archivos
            logger()->debug('Starting files backup');
            $this->backupFiles($filename);

            // Comprimir
            logger()->debug('Compressing backup');
            $this->compress($filename);

            // Limpiar backups antiguos
            logger()->debug('Rotating old backups');
            $this->rotate();

            // Registrar backup en la base de datos
            $conn = $this->em->getConnection();
            $conn->executeStatement(
                'INSERT INTO backups (filename, created_at) VALUES (?, NOW())',
                [$filename . '.zip']
            );

            logger()->info('Backup completed successfully', ['filename' => $filename]);
            return true;
        } catch (\Exception $e) {
            logger()->error('Backup failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    private function backupDatabase(string $filename): void
    {
        logger()->debug('Creating database dump', [
            'host' => $_ENV['DB_HOST'],
            'database' => $_ENV['DB_NAME'],
            'output' => "{$this->backupPath}/{$filename}.sql"
        ]);

        $command = sprintf(
            'mysqldump -h%s -u%s -p%s %s > %s/%s.sql',
            $_ENV['DB_HOST'],
            $_ENV['DB_USER'],
            $_ENV['DB_PASS'],
            $_ENV['DB_NAME'],
            $this->backupPath,
            $filename
        );

        exec($command, $output, $return);

        if ($return !== 0) {
            logger()->error('Database dump failed', [
                'return_code' => $return,
                'output' => $output
            ]);
            throw new \RuntimeException('Database backup failed');
        }

        logger()->debug('Database dump completed');
    }

    private function backupFiles(string $filename): void
    {
        $source = dirname(__DIR__, 2);
        $dest = "{$this->backupPath}/{$filename}_files";

        logger()->debug('Starting files backup', [
            'source' => $source,
            'destination' => $dest
        ]);

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source)
        );

        mkdir($dest);
        $fileCount = 0;

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                continue;
            }

            $target = str_replace($source, $dest, $file->getPathname());
            $targetDir = dirname($target);

            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }

            copy($file->getPathname(), $target);
            $fileCount++;
        }

        logger()->debug('Files backup completed', ['total_files' => $fileCount]);
    }

    private function compress(string $filename): void
    {
        $zip = new \ZipArchive();
        $zipFile = "{$this->backupPath}/{$filename}.zip";

        logger()->debug('Creating zip archive', ['zip_file' => $zipFile]);

        if ($zip->open($zipFile, \ZipArchive::CREATE) !== true) {
            logger()->error('Failed to create zip file', ['zip_file' => $zipFile]);
            throw new \RuntimeException('Could not create zip file');
        }

        // Agregar archivo SQL
        logger()->debug('Adding SQL file to zip');
        $zip->addFile("{$this->backupPath}/{$filename}.sql");

        // Agregar directorio de archivos
        logger()->debug('Adding files to zip');
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator("{$this->backupPath}/{$filename}_files")
        );

        $fileCount = 0;
        foreach ($iterator as $file) {
            if ($file->isDir()) {
                continue;
            }

            $zip->addFile(
                $file->getPathname(),
                str_replace("{$this->backupPath}/{$filename}_files/", '', $file->getPathname())
            );
            $fileCount++;
        }

        $zip->close();
        logger()->debug('Zip archive created', ['total_files' => $fileCount]);

        // Limpiar archivos temporales
        unlink("{$this->backupPath}/{$filename}.sql");
        $this->removeDirectory("{$this->backupPath}/{$filename}_files");
        logger()->debug('Temporary files cleaned up');
    }

    private function rotate(): void
    {
        $files = glob("{$this->backupPath}/*.zip");
        usort($files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        logger()->debug('Starting backup rotation', [
            'total_backups' => count($files),
            'max_backups' => self::MAX_BACKUPS
        ]);

        while (count($files) > self::MAX_BACKUPS) {
            $file = array_pop($files);
            logger()->info('Removing old backup', ['file' => basename($file)]);
            unlink($file);
        }
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        logger()->debug('Removing directory', ['directory' => $dir]);

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }

        rmdir($dir);
    }

    public function restore(string $backup): bool
    {
        try {
            logger()->info('Starting backup restore', ['backup' => $backup]);

            $zip = new \ZipArchive();

            if ($zip->open("{$this->backupPath}/{$backup}") !== true) {
                logger()->error('Could not open backup file', ['backup' => $backup]);
                throw new \RuntimeException('Could not open backup file');
            }

            // Crear directorio temporal
            $tempDir = "{$this->backupPath}/restore_" . uniqid();
            logger()->debug('Created temporary directory', ['directory' => $tempDir]);
            mkdir($tempDir);

            // Extraer backup
            logger()->debug('Extracting backup');
            $zip->extractTo($tempDir);
            $zip->close();

            // Restaurar base de datos
            logger()->debug('Restoring database');
            $command = sprintf(
                'mysql -h%s -u%s -p%s %s < %s/backup.sql',
                $_ENV['DB_HOST'],
                $_ENV['DB_USER'],
                $_ENV['DB_PASS'],
                $_ENV['DB_NAME'],
                $tempDir
            );

            exec($command, $output, $return);

            if ($return !== 0) {
                logger()->error('Database restore failed', [
                    'return_code' => $return,
                    'output' => $output
                ]);
                throw new \RuntimeException('Database restore failed');
            }

            // Restaurar archivos
            logger()->debug('Restoring files');
            $source = $tempDir;
            $dest = dirname(__DIR__, 2);

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($source)
            );

            $fileCount = 0;
            foreach ($iterator as $file) {
                if ($file->isDir()) {
                    continue;
                }

                $target = str_replace($source, $dest, $file->getPathname());
                $targetDir = dirname($target);

                if (!is_dir($targetDir)) {
                    mkdir($targetDir, 0755, true);
                }

                copy($file->getPathname(), $target);
                $fileCount++;
            }

            // Limpiar
            $this->removeDirectory($tempDir);

            logger()->info('Restore completed successfully', [
                'backup' => $backup,
                'files_restored' => $fileCount
            ]);
            return true;
        } catch (\Exception $e) {
            logger()->error('Restore failed', [
                'backup' => $backup,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    public function getBackupList(): array
    {
        try {
            logger()->debug('Fetching backup list');
            $conn = $this->em->getConnection();
            $backups = $conn->executeQuery(
                'SELECT * FROM backups ORDER BY created_at DESC'
            )->fetchAllAssociative();

            logger()->debug('Backup list retrieved', ['count' => count($backups)]);
            return $backups;
        } catch (\Exception $e) {
            logger()->error('Failed to get backup list', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
}