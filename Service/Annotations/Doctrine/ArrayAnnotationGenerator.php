<?php

namespace Requestum\ApiGeneratorBundle\Service\Annotations\Doctrine;

use Requestum\ApiGeneratorBundle\Model\EntityProperty;
use Requestum\ApiGeneratorBundle\Model\Enum\PropertyTypeEnum;
use Requestum\ApiGeneratorBundle\Service\Annotations\AnnotationGeneratorInterface;
use Requestum\ApiGeneratorBundle\Service\Annotations\AnnotationRecord;

/**
 * Class ArrayAnnotationGenerator
 *
 * @package Requestum\ApiGeneratorBundle\Service\Annotations\Doctrine
 */
class ArrayAnnotationGenerator implements AnnotationGeneratorInterface
{
    /**
     * @param EntityProperty $entityProperty
     * @param AnnotationRecord $annotationRecord
     *
     * @return AnnotationRecord
     */
    public function generate(EntityProperty $entityProperty, AnnotationRecord $annotationRecord): AnnotationRecord
    {
        // TODO: Implement generate() method.

        return $annotationRecord;
    }

    /**
     * @param EntityProperty $entityProperty
     *
     * @return bool
     */
    public function support(EntityProperty $entityProperty): bool
    {
        // TODO: Implement support() method.

        return $entityProperty->getType() === PropertyTypeEnum::TYPE_ARRAY;
    }
}
