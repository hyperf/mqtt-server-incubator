<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace Hyperf\MqttServer\Handler;

use Psr\Http\Message\ResponseInterface;

trait ResponseRewritable
{
    /**
     * When the body of response is written by custom connect handler.
     * It is no need to rewrite again.
     */
    public function isRewritable(ResponseInterface $response): bool
    {
        return ((string) $response->getBody()) === '';
    }
}
