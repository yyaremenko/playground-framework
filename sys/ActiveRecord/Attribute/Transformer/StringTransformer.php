<?php

namespace Sys\ActiveRecord\Attribute\Transformer;

class StringTransformer implements TransformerInterface
{
    public function modelToDb(mixed $modelValue): mixed
    {
        return (string) $modelValue;
    }

    public function dbToModel(mixed $dbValue): mixed
    {
        return (string) $dbValue;
    }
}
