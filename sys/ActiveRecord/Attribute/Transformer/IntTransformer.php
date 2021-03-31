<?php

namespace Sys\ActiveRecord\Attribute\Transformer;

#[\Attribute]
class IntTransformer implements TransformerInterface
{
    public function modelToDb(mixed $modelValue): int
    {
        return (int) $modelValue;
    }

    public function dbToModel(mixed $dbValue): int
    {
        return (int) $dbValue;
    }
}
