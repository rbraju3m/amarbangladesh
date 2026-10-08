<?php

use App\Support\Lang;

if (! function_exists('lroute')) {
    /** A named public route in the current language. See App\Support\Lang. */
    function lroute(string $name, mixed $parameters = [], bool $absolute = false): string
    {
        return Lang::route($name, $parameters, $absolute);
    }
}
