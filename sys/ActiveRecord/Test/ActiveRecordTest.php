<?php

namespace Sys\ActiveRecord\Test;

use PHPUnit\Framework\TestCase;
use Sys\ActiveRecord\AbstractActiveRecord;

class ActiveRecordTest extends TestCase
{

}

class SomeActiveRecord extends AbstractActiveRecord
{
    protected static function getTableName(): string
    {
        return 'some_table_name';
    }
}
