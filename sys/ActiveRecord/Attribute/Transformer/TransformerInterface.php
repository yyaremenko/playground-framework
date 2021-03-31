<?php

namespace Sys\ActiveRecord\Attribute\Transformer;

interface TransformerInterface
{
    public function modelToDb(mixed $modelValue): mixed;
    public function dbToModel(mixed $dbValue): mixed;
}
