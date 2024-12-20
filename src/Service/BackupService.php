<?php
namespace App\Service;

use App\Service\Logger\AppLogger;
use Doctrine\ORM\EntityManagerInterface;

class BackupService
{
    private const MAX_BACKUPS = 7; // Mantener últimos 7 backups

    private EntityManagerInterface $em;
    private AppLogger $logger;
    private string $backupPath;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
        $this->logger = new AppLogger();
        $this->backupPath = $_ENV['BACKUP_PATH'] ?? dirname(__DIR__, 2) . '/backups';
        
        if (!is_dir($this->backupPath)) {
            mkdir($this->backupPath, 0755, true);
        }
    }

    public function createBackup(): bool
    {
        try {
            $filename = date('Y-m-d_His') . '_backup';
            
            // Backup de la base de datos
            $this->backupDatabase($filename);
            
            // Backup de archivos
            $this->backupFiles($filename);
            
            // Comprimir
            $this->compress($filename);
            
            // Limpiar backups antiguos
            $this->rotate();

            // Registrar backup en la base de datos
            $conn = $this->em->getConnection();
            $conn->executeStatement(
                'INSERT INTO backups (filename, created_at) VALUES (?, NOW())',
                [$filename . '.zip']
            );
            
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Backup failed', [], $e);
            return false;
        }
    }

    private function backupDatabase(string $filename): void
    {
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
            throw new \RuntimeException('Database backup failed');
        }
    }

    private function backupFiles(string $filename): void
    {
        $source = dirname(__DIR__, 2);
        $dest = "{$this->backupPath}/{$filename}_files";
        
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source)
        );
        
        mkdir($dest);
        
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
        }
    }

    private function compress(string $filename): void
    {
        $zip = new \ZipArchive();
        $zipFile = "{$this->backupPath}/{$filename}.zip";
        
        if ($zip->open($zipFile, \ZipArchive::CREATE) !== true) {
            throw new \RuntimeException('Could not create zip file');
        }
        
        // Agregar archivo SQL
        $zip->addFile("{$this->backupPath}/{$filename}.sql");
        
        // Agregar directorio de archivos
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator("{$this->backupPath}/{$filename}_files")
        );
        
        foreach ($iterator as $file) {
            if ($file->isDir()) {
                continue;
            }
            
            $zip->addFile(
                $file->getPathname(),
                str_replace("{$this->backupPath}/{$filename}_files/", '', $file->getPathname())
            );
        }
        
        $zip->close();
        
        // Limpiar archivos temporales
        unlink("{$this->backupPath}/{$filename}.sql");
        $this->removeDirectory("{$this->backupPath}/{$filename}_files");
    }

    private function rotate(): void
    {
        $files = glob("{$this->backupPath}/*.zip");
        usort($files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        
        while (count($files) > self::MAX_BACKUPS) {
            $file = array_pop($files);
            unlink($file);
        }
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        
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
            $zip = new \ZipArchive();
            
            if ($zip->open("{$this->backupPath}/{$backup}") !== true) {
                throw new \RuntimeException('Could not open backup file');
            }
            
            // Crear directorio temporal
            $tempDir = "{$this->backupPath}/restore_" . uniqid();
            mkdir($tempDir);
            
            // Extraer backup
            $zip->extractTo($tempDir);
            $zip->close();
            
            // Restaurar base de datos
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
                throw new \RuntimeException('Database restore failed');
            }
            
            // Restaurar archivos
            $source = $tempDir;
            $dest = dirname(__DIR__, 2);
            
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($source)
            );
            
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
            }
            
            // Limpiar
            $this->removeDirectory($tempDir);
            
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Restore failed', ['backup' => $backup], $e);
            return false;
        }
    }

    public function getBackupList(): array
    {
        try {
            $conn = $this->em->getConnection();
            return $conn->executeQuery(
                'SELECT * FROM backups ORDER BY created_at DESC'
            )->fetchAllAssociative();
        } catch (\Exception $e) {
            $this->logger->error('Failed to get backup list', [], $e);
            return [];
        }
    }
}