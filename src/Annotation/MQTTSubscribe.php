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

namespace Hyperf\MqttServer\Annotation;

use Attribute;
use Simps\MQTT\Protocol\Types;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class MQTTSubscribe extends MQTTEvent
{
    /**
     * @var int
     */
    public $type = Types::SUBSCRIBE;
}
