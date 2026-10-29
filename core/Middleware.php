<?php
/**
 * Base Middleware interface.
 * All middleware must extend this and implement handle().
 */

namespace Core;

abstract class Middleware
{
    /**
     * Handle the request.
     * Should either allow passage or redirect/exit.
     *
     * @param Request $request
     * @return void
     */
    abstract public function handle(Request $request): void;
}
