<?php

declare(strict_types=1);

namespace Pollora\HiddenLogin\Port\Out;

/**
 * Answers a request with a 404, as if the requested endpoint did not exist.
 *
 * Split in two steps for the same reason as
 * {@see LoginScreenRendererPort}: the request has to be neutralised before
 * WordPress parses it, and the response can only be produced once the query and
 * rewrite objects exist.
 */
interface NotFoundResponderPort
{
    /**
     * Neutralises the request so that WordPress resolves it to nothing.
     *
     * Implementations are expected to point the request at a path that cannot
     * match any content and to discard the incoming query and body, so that no
     * hook down the line acts on the original — blocked — request.
     */
    public function prepare(): void;

    /**
     * Sends the 404 response and terminates the request.
     */
    public function respond(): never;
}
