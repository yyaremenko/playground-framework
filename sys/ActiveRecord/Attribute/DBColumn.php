<?php

namespace Sys\ActiveRecord\Attribute;

#[\Attribute]
class DBColumn
{
    public function __construct(
        public string $name,
        public bool $includeInCreate = true,
        public bool $includeInUpdate = true,
    ) {}
}