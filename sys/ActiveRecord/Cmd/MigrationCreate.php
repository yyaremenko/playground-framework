<?php

namespace Sys\ActiveRecord\Cmd;

use Sys\Cmd\AbstractCommand;

class MigrationCreate extends AbstractCommand
{
    public static function getName(): string
    {
        return 'migration:create';
    }

    public static function getDescription(): string
    {
        return 'Create a template for a new migration';
    }

    public function run(): void
    {
        $timestamp = (new \DateTime)->getTimestamp();
        $fileName = sprintf('Migration%s.php', $timestamp);
        $filePath = __DIR__ . '/../Migration/' . $fileName;

        $file = fopen($filePath, 'w') or die ('Can not create file at ' . $filePath);
        fwrite($file, <<<EOT
        <?php

        namespace Sys\Migration;
        
        class Migration$timestamp extends AbstractMigration
        {
            public static function getTimestampKey(): int
            {
                return $timestamp;
            }
            
            public static function getDescription(): string
            {
                // TODO implement description
            }
        
            public function apply(): void
            {
                // TODO implement migration apply
            }
            
            public function revert(): void
            {
                // TODO implement migration revert
            }
        }

        EOT
        );

        fclose($file);

        $realpath = realpath($filePath);
        echo <<<EOT
        
        ----------------------------------------------
        Migration successfully created at:
        $realpath
        
        You can now fill it with needed queries.
        ---------------------------------------------- 


        EOT;
    }
}
