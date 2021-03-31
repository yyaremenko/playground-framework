<?php

namespace Sys\ActiveRecord;

use Sys\ActiveRecord\Attribute\DBColumn;
use Sys\ActiveRecord\Attribute\Transformer\TransformerInterface;
use Sys\ActiveRecord\Attribute\Transformer\IntTransformer;

abstract class AbstractActiveRecord
{
    private const QUERY_PARAMS_DELIMITER = ',';
    private const QUERY_PLACEHOLDER_PREFIX = ':';

    protected static ?\PDO $connection;

    #[DBColumn(name: 'id', includeInCreate: false, includeInUpdate: false)]
    #[IntTransformer]
    protected ?int $id = null;

    // must implement these methods

    abstract protected static function getTableName(): string;

    // do not use constructor directly to create a new instance,
    // as this will interfere wit converting Models from DB;
    // write instead custom static methods, e.g. User::newFrom(name: 'Joe', lastName: 'Doe');
    protected function __construct() {}

    // init / deinit

    public static function init(
        string $serverName,
        string $dbName,
        string $userName,
        string $password,
    ): \PDO {
        $dataSourceName = sprintf(
            'mysql:host=%s;dbname=%s',
            $serverName,
            $dbName,
        );
        self::$connection = new \PDO($dataSourceName, $userName, $password);

        return self::$connection;
    }

    public static function deInit(): void
    {
        // close connection
        self::$connection = null;
    }

    // accessors

    public function getId(): ?int
    {
        return $this->id;
    }

    // DB::READ

    /**
     * @param array $where
     * @return AbstractActiveRecord[]
     * @throws \Exception
     */
    public static function fetchAll(array $where = []): array
    {
        $rClass = new \ReflectionClass(static::class);
        $whereData = [];

        foreach ($where as $propName => $whereValue) {
            $rProperty = $rClass->getProperty($propName);
            $dbColumn = self::getDbColumnFromRProperty($rProperty);
            if (!$dbColumn) {
                throw new \Exception('No mapping info for the given proeprty found.');
            }

            $whereData[$dbColumn->name] = $whereValue;
        }

        $columnPlaceholderPairs = self::getColumnPlaceholderPairs(array_keys($whereData));
        $whereSql = implode(self::QUERY_PARAMS_DELIMITER, $columnPlaceholderPairs);

        // if no 'where' statement is provided, make a stub
        !$whereSql && $whereSql = true;

        $statement = self::$connection->prepare(
            sprintf(
                'SELECT * FROM %s WHERE %s',
                static::getTableName(),
                $whereSql,
            )
        );
        self::bindValues($statement, $whereData);
        $statement->execute();

        /** @var static[] $instances */
        $instances = [];
        $rows = $statement->fetchAll();
        $rowsCount = count($rows);
        if (0 === $rowsCount) {
            return $instances;
        }

        // pre-fill array with objects of given class
        $instances = array_fill(0, $rowsCount, (new static()));

        // for each property, fill each instance
        // with corresponding value from the database,
        // transformed, if needed
        foreach (self::getNonStaticReflectionProperties($rClass) as $rProperty) {
            $dbColumn = self::getDbColumnFromRProperty($rProperty);
            if (!$dbColumn) {
                continue;
            }

            $rProperty->setAccessible(true);
            for ($i = 0; $i < $rowsCount; $i++) {
                $currentInstance = $instances[$i];
                $dbValue = $rows[$i][$dbColumn->name];
                $modelValue = self::getValueTransformed(
                    $rProperty,
                    isModelToDb: false,
                    value: $dbValue,
                );
                $rProperty->setValue($currentInstance, $modelValue);
            }
        }

        return $instances;
    }

    // DB::WRITE

    public function save(): void
    {
        $id = $this->getId();
        if ($id) {
            $this->update($id);
        } else {
            $this->create();
        }
    }

    protected function create(): void
    {
        $dataToWrite = $this->getDataToWriteToDb(true);

        $columnsToWrite = array_keys($dataToWrite);
        $columnsSql = implode(self::QUERY_PARAMS_DELIMITER, $columnsToWrite);
        $placeholdersSql = implode(
            self::QUERY_PARAMS_DELIMITER,
            array_map(fn($colName) => self::getPlaceholder($colName), $columnsToWrite)
        );

        $statement = self::$connection->prepare(
            sprintf(
                'INSERT INTO %s (%s) values (%s)',
                static::getTableName(),
                $columnsSql,
                $placeholdersSql,
            )
        );
        self::bindValues($statement, $dataToWrite);
        $statement->execute();

        $this->id = self::$connection->lastInsertId();
    }

    protected function update(int $id): void
    {
        $dataToWrite = $this->getDataToWriteToDb(false);
        $columnPlaceholderPairs = self::getColumnPlaceholderPairs(array_keys($dataToWrite));
        $sqlSet = implode(self::QUERY_PARAMS_DELIMITER, $columnPlaceholderPairs);

        $statement = self::$connection->prepare(
            sprintf(
                'UPDATE %s SET %s WHERE id = :id',
                static::getTableName(),
                $sqlSet
            )
        );
        self::bindValues($statement, $dataToWrite);
        $statement->bindValue(':id', $id, \PDO::PARAM_INT);
        $statement->execute();
    }

    // DB::DELETE

    public function delete(): void
    {
        $id = $this->getId();
        if (!$id) {
            return;
        }

        $statement = self::$connection->prepare(
            sprintf(
                'DELETE FROM %s WHERE id = :id',
                static::getTableName(),
            )
        );
        $statement->bindValue(':id', $id, \PDO::PARAM_INT);

        $isDeleteSuccess = $statement->execute();
        if ($isDeleteSuccess) {
            $this->id = null; // TODO clear all other properties, too?
        }
    }

    // misc

    private function getDataToWriteToDb(bool $isCreateOperation): array
    {
        $rClass = new \ReflectionClass($this);
        $dataToWrite = [];

        foreach (self::getNonStaticReflectionProperties($rClass) as $rProperty) {
            $dbColumn = self::getDbColumnFromRProperty($rProperty);
            if (!$dbColumn) {
                continue;
            }

            $ignoreThisColumn = ($isCreateOperation && !$dbColumn->includeInCreate)
                || (!$isCreateOperation && !$dbColumn->includeInUpdate);
            if ($ignoreThisColumn) {
                continue;
            }

            $rProperty->setAccessible(true);
            $modelValue = $rProperty->getValue($this);
            $dbValue = self::getValueTransformed(
                $rProperty,
                isModelToDb: true,
                value: $modelValue
            );
            $dataToWrite[$dbColumn->name] = $dbValue;
        }

        return $dataToWrite;
    }

    protected static function getDbColumn(AbstractActiveRecord $target, string $propName): ?DBColumn
    {
        return self::getDbColumnFromRProperty(
            new \ReflectionProperty($target, $propName)
        );
    }

    private static function getDbColumnFromRProperty(\ReflectionProperty $rProperty): ?DBColumn
    {
        /** @var \ReflectionAttribute[] $rDbColAttribs */
        $rDbColAttribs = $rProperty->getAttributes(DBColumn::class);
        if (0 === count($rDbColAttribs)) {
            return null;
        }

        return $rDbColAttribs[0]->newInstance();
    }

    private static function bindValues(\PDOStatement $statement, array $dataToBind)
    {
        foreach ($dataToBind as $colName => $colValue) {
            $statement->bindValue(
                self::getPlaceholder($colName),
                $colValue,
            );
        }
    }

    private static function getValueTransformed(
        \ReflectionProperty $rProperty,
        bool $isModelToDb,
        mixed $value,
    ): mixed {
        $rTransformerAttribs = $rProperty->getAttributes(
            TransformerInterface::class,
            \ReflectionAttribute::IS_INSTANCEOF,
        );
        if (0 === count($rTransformerAttribs)) {
            return $value;
        }

        /** @var TransformerInterface $transformer */
        $transformer = $rTransformerAttribs[0]->newInstance();

        return $isModelToDb
            ? $transformer->modelToDb($value)
            : $transformer->dbToModel($value)
            ;
    }

    private static function getColumnPlaceholderPairs(array $columns): array
    {
        return array_map(
            fn($colName) => sprintf(
                '%s = %s',
                $colName,
                self::getPlaceholder($colName)
            ),
            $columns
        );
    }

    /**
     * @param \ReflectionClass $rClass
     * @return \ReflectionProperty[]
     */
    private static function getNonStaticReflectionProperties(\ReflectionClass $rClass): array
    {
        return array_filter($rClass->getProperties(), fn($rProperty) => !$rProperty->isStatic());
    }

    private static function getPlaceholder(string $colName): string
    {
        return self::QUERY_PLACEHOLDER_PREFIX . $colName;
    }
}
