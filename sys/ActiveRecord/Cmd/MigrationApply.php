<?php

namespace Sys\ActiveRecord\Cmd;

use Sys\bin\Cmd;
use Sys\Migration\AbstractMigration;
use Sys\Cmd\AbstractCommand;

class MigrationApply extends AbstractCommand
{
    public static function getName(): string
    {
        return 'migration:apply';
    }

    public static function getDescription(): string
    {
        return 'Execute not yet executed migrations';
    }

    public function run(): void
    {
        require_once __DIR__ . '/../../config.php';
        $connection = MigrationRecord::init(
            $db_server_name,
            $db_name,
            $db_username,
            $db_password
        );

        // TODO implement transaction
        try {
            $this->doRun($connection);

        } catch (\Throwable $e) {
            $eMessage = sprintf(
                'Can not proceed. An error occurred: %s. The trace is: %s',
                $e->getMessage(),
                print_r($e->getTrace(), true)
            );
            $this->end($eMessage);
        }
    }

    private function doRun(\PDO $connection): void
    {
        $this->createMigrationsTableIfNotExists($connection);
        $appliedMigrationsKeys = array_map(
            fn(MigrationRecord $migration) => $migration->getTimestampKey(),
            MigrationRecord::fetchAll()
        );

        $migrationClasses = Cmd\loadNonAbstractClassesFromDir(__DIR__ . '/../Migration/', 'Sys\Migration\\');
        $migrationsToRun = [];
        foreach ($migrationClasses as $mClass) {
            $key = $mClass::getTimestampKey();
            if (in_array($key, $appliedMigrationsKeys)) {
                continue;
            }

            $migrationsToRun[$key] = $mClass;
        }

        // make sure earlier migrations go first,
        // later migrations go after
        ksort($migrationsToRun);

        foreach ($migrationsToRun as $mClass) {
            /** @var AbstractMigration $migration */
            $migration = new $mClass($connection);
            $migration->apply();
            echo sprintf('...applied: %s', $mClass::getDescription()) . PHP_EOL;

            $mRecord = MigrationRecord::newFrom($mClass::getTimestampKey(), new \DateTime);
            $mRecord->save();
        }

        $numOfAppliedMigrations = count($migrationsToRun);
        $endMsg = 0 === $numOfAppliedMigrations
            ? 'Nothing to migrate'
            : sprintf('Successfully applied %d migration(s)', $numOfAppliedMigrations)
        ;

        $this->end($endMsg);
    }

    private function createMigrationsTableIfNotExists(\PDO $connection): void
    {
        $statement = $connection->prepare(
            '
            create table if not exists `migrations` (
                id int(10) not null auto_increment,
                timestamp_key int(10) not null unique,
                applied_at datetime not null,
                primary key (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1;
            '
        );
        $statement->execute();
    }

    private function end(string $message): void
    {
        MigrationRecord::deInit();
        echo PHP_EOL . $message . PHP_EOL;
        exit;
    }
}