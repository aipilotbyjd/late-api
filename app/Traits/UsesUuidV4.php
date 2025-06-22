<?php

namespace App\Traits;

use Illuminate\Support\Str;

trait UsesUuidV4
{
    /**
     * Boot the trait and set up the model to use UUIDv4.
     */
    protected static function bootUsesUuidV4()
    {
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }

    /**
     * Get the incrementing status of the model.
     *
     * @return bool
     */
    public function getIncrementing()
    {
        return false;
    }

    /**
     * Get the key type for the model.
     *
     * @return string
     */
    public function getKeyType()
    {
        return 'string';
    }
}
