<?php

namespace PocketArc\HorizonDatabase\Exceptions;

use RuntimeException;

class JobLostException extends RuntimeException
{
    /**
     * Create an exception for a job whose worker died before reporting back.
     *
     * @param  string  $id
     * @param  string  $connection
     * @return self
     */
    public static function forJob($id, $connection)
    {
        return new self(
            "Job [{$id}] on connection [{$connection}] was reserved but never "
            .'completed or released. Its worker most likely died before it '
            .'could report the failure.'
        );
    }
}
