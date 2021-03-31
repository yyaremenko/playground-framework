<?php

namespace Sys\ActiveRecord\Attribute\Transformer;

#[\Attribute]
class ArrayTransformer implements TransformerInterface
{
    public function modelToDb(mixed $modelValue): string
    {
        if (!is_array($modelValue)) {
            throw new \Exception('Expected array.');
        }

        return json_encode($modelValue);
    }

    public function dbToModel(mixed $dbValue): array
    {
        if (!is_string($dbValue)) {
            throw new \Exception('Expected string.');
        }

        return json_decode($dbValue);
    }
}
