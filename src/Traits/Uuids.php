<?php namespace Foothing\Laravel\Consent\Traits;

use Illuminate\Support\Str;

trait Uuids {

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model)
        {
            $model->{$model->getKeyName()} = Str::uuid();
        });
    }
}
