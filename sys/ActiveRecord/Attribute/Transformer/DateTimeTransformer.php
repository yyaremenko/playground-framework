<?php

namespace Sys\ActiveRecord\Attribute\Transformer;

#[\Attribute]
class DateTimeTransformer implements TransformerInterface
{
    private const FORMAT = 'Y-m-d H:i:s';

    public function modelToDb(mixed $modelValue): mixed
    {
        if (!$modelValue instanceof \DateTime) {
            throw new \Exception('Expected \DateTime');
        }

        return $modelValue->format(self::FORMAT);
    }

    public function dbToModel(mixed $dbValue): mixed
    {
        return new \DateTime($dbValue);
    }
}
