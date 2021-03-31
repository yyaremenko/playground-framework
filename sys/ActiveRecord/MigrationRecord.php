<?php

namespace Sys\ActiveRecord;

use Sys\ActiveRecord\Attribute\DBColumn;
use Sys\ActiveRecord\Attribute\Transformer\IntTransformer;
use Sys\ActiveRecord\Attribute\Transformer\DateTimeTransformer;

class MigrationRecord extends AbstractActiveRecord
{
    private const TABLE_NAME = 'migrations';

    #[DBColumn('timestamp_key')]
    #[IntTransformer]
    private int $timestampKey;

    #[DBColumn('applied_at')]
    #[DateTimeTransformer]
    private \DateTime $appliedAt;

    protected static function getTableName(): string
    {
        return self::TABLE_NAME;
    }

    public static function newFrom(int $timestampKey, \DateTime $appliedAt): self
    {
        $instance = new self();
        $instance->timestampKey = $timestampKey;
        $instance->appliedAt = $appliedAt;

        return $instance;
    }

    public function getTimestampKey(): int
    {
        return $this->timestampKey;
    }
}
