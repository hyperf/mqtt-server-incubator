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
use Hyperf\Di\Annotation\AbstractAnnotation;
use Hyperf\Di\Annotation\AnnotationCollector;

/**
 * @Annotation
 * @Target({"CLASS", "METHOD"})
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class MQTTEvent extends AbstractAnnotation
{
    /**
     * @var string
     */
    public $server = 'mqtt';

    /**
     * @var int
     */
    public $type = 0;

    /**
     * @var int
     */
    public $priority = 0;

    public function collectClass(string $className): void
    {
        AnnotationCollector::collectMethod($className, 'handle', MQTTEvent::class, $this);
    }

    public function collectMethod(string $className, ?string $target): void
    {
        AnnotationCollector::collectMethod($className, $target, MQTTEvent::class, $this);
    }
}
