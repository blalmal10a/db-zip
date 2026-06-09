<?php

namespace Blalmal10a\DbZip\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Blalmal10a\DbZip\DbZip
 */
class DbZip extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Blalmal10a\DbZip\DbZip::class;
    }
}
