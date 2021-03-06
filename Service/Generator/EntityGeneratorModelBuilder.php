<?php

namespace Requestum\ApiGeneratorBundle\Service\Generator;

use Requestum\ApiGeneratorBundle\Exception\AccessLevelException;
use Requestum\ApiGeneratorBundle\Exception\SubjectTypeException;
use Requestum\ApiGeneratorBundle\Helper\StringHelper;
use Requestum\ApiGeneratorBundle\Model\Entity;
use Requestum\ApiGeneratorBundle\Model\Generator\AccessLevelEnum;
use Requestum\ApiGeneratorBundle\Model\Generator\GeneratorMethodModel;
use Requestum\ApiGeneratorBundle\Model\Generator\GeneratorParameterModel;
use Requestum\ApiGeneratorBundle\Model\Generator\GeneratorPropertyModel;
use Requestum\ApiGeneratorBundle\Model\EntityProperty;
use Requestum\ApiGeneratorBundle\Model\Enum\PropertyTypeEnum;
use Requestum\ApiGeneratorBundle\Service\Annotations\AnnotationGenerator;

/**
 * Class EntityGeneratorModelBuilder
 *
 * @package Requestum\ApiGeneratorBundle\Service\Generator
 */
class EntityGeneratorModelBuilder extends GeneratorModelBuilderAbstract
{
    /**
     * @var AnnotationGenerator
     */
    protected AnnotationGenerator $annotationGenerator;

    /** @var array */
    protected array $annotations = [];

    /**
     * EntityGeneratorModelBuilder constructor.
     *
     * @param string $bundleName
     */
    public function __construct(string $bundleName)
    {
        parent::__construct($bundleName);

        $this->annotationGenerator = new AnnotationGenerator();
    }

    /**
     * @param Entity|object $entity
     *
     * @return ClassGeneratorModelInterface
     *
     * @throws AccessLevelException
     */
    public function buildModel(object $entity): ClassGeneratorModelInterface
    {
        if (!$entity instanceof Entity) {
            throw new SubjectTypeException($entity, Entity::class);
        }

        $entity->getInterfaces();

        $this->baseAnnotations($entity->getName(), $entity->getTableName());
        $this->addAnnotations($entity->getAnnotations());
        $this->addTraits($entity->getTraits());
        $this->detectConstructor($entity);
        $this->prepareConstants($entity);
        $this->prepareProperties($entity->getProperties());
        $this->prepareMethods($entity->getProperties());
        $this->prepareInterfaces($entity);

        $nameSpace = implode('\\', [$this->bundleName, 'Entity']);
        $model = new ClassGeneratorModel();

        $model
            ->setName($entity->getName())
            ->setNameSpace($nameSpace)
            ->setFilePath(
                $this->prepareFilePath($entity->getName())
            )
            ->setTraits($this->traits)
            ->setAnnotations($this->annotations)
            ->setUseSection($this->useSection)
            ->setConstants($this->constants)
            ->setProperties($this->properties)
            ->setMethods($this->methods)
            ->setImplementedInterfaces($this->interfaces)
        ;

        return $model;
    }

    /**
     * @param string $entityName
     * @param string $tableName
     */
    private function baseAnnotations(string $entityName, string $tableName)
    {
        $this->annotations[] = sprintf('@ORM\Table(name="`%s`")', $tableName);

        // AppBundle\Repository\SomeRepository
        $repositoryClass = implode('\\', [$this->bundleName, 'Repository', implode('', [$entityName, 'Repository'])]);
        $this->annotations[] = sprintf('@ORM\Entity(repositoryClass="%s")', $repositoryClass);
    }

    /**
     * @param array $annotations
     */
    private function addAnnotations(array $annotations = [])
    {
        array_push($this->annotations, ...$annotations);
    }

    /**
     * @param Entity $entity
     */
    private function prepareConstants(Entity $entity)
    {
        $properties = $entity->getPropertiesEnum();
        if (is_array($properties) && !empty($properties)) {
            /** @var  $property */
            foreach ($properties as $property) {
                if (!is_array($property->getEnum()) || empty($property->getEnum())) {
                    continue;
                }

                foreach ($property->getEnum() as $enum) {
                    $constantName = implode('_', [strtoupper($property->getDatabasePropertyName()), strtoupper($enum)]);
                    $this->constants[] = [
                        'name' => $constantName,
                        'value' => strtolower($enum)
                    ];
                }
            }
        }
    }

    /**
     * @param Entity $entity
     */
    private function detectConstructor(Entity $entity)
    {
        $properties = $entity->getOneToManyProperties();
        if (is_array($properties) && count($properties) > 0) {
            $body = [];
            /** @var EntityProperty $property */
            foreach ($properties as $property) {
                $body[] = sprintf('$this->%s = new ArrayCollection();' . PHP_EOL , $property->getName());
            }
            $construct = new GeneratorMethodModel();
            $construct
                ->setName('__construct')
                ->setAccessLevel(AccessLevelEnum::ACCESS_LEVEL_PUBLIC)
                ->setBody(implode('', $body))
            ;

            $this->methods[] = $construct;
            $this->useSection[] = 'Doctrine\Common\Collections\ArrayCollection';
        }
    }

    /**
     * @param string $entityName
     *
     * @return string
     */
    private function prepareFilePath(string $entityName)
    {
        return implode('.', [$entityName, 'php']);
    }

    /**
     * @param array $properties
     *
     * @throws AccessLevelException
     * @throws \Exception
     */
    private function prepareProperties(array $properties)
    {
        /** @var EntityProperty $entityProperty */
        foreach ($properties as $entityProperty) {
            $property = new GeneratorPropertyModel();
            $property
                ->setName($entityProperty->getName())
                ->setAccessLevel(AccessLevelEnum::ACCESS_LEVEL_PROTECTED)
                ->setAttributes(
                    $this->getPropertyAttributes($entityProperty)
                )
            ;

            $this->properties[] = $property;
        }
    }

    /**
     * @param EntityProperty $entityProperty
     *
     * @return array
     *
     * @throws \Exception
     */
    private function getPropertyAttributes(EntityProperty $entityProperty): array
    {
        $annotationRecord = $this->annotationGenerator->getAnnotationRecord($entityProperty);
        $annotationRecord->addAnnotations($entityProperty->getAnnotations());
        $annotationRecord->addAnnotations(
                [
                    sprintf('var %s $%s', $entityProperty->getType(), $entityProperty->getName())
                ]
        );

        $this->addUseSections($annotationRecord->getUseSection());

        return $annotationRecord->getAnnotation();
    }

    /**
     * @param array $properties
     */
    private function prepareMethods(array $properties)
    {
        /** @var EntityProperty $entityProperty */
        foreach ($properties as $entityProperty) {
            $setter = new GeneratorMethodModel();
            $getter = new GeneratorMethodModel();

            $parameterType = $this->prepareParameterType($entityProperty);

            $setterInputParameter = new GeneratorParameterModel();
            $setterInputParameter
                ->setName($entityProperty->getName())
                ->setType($parameterType)
            ;

            $setterReturnParameter = new GeneratorParameterModel();
            $setterReturnParameter
                ->setType($entityProperty->getEntity()->getName())
                ->setIsReturn(true)
            ;

            $setter
                ->setName(StringHelper::makeSetterName($entityProperty->getName()))
                ->setAccessLevel(AccessLevelEnum::ACCESS_LEVEL_PUBLIC)
                ->setBody(sprintf('$this->%s = $%s;' . PHP_EOL . PHP_EOL . 'return $this;', $entityProperty->getName(), $entityProperty->getName()))
                ->addParameters($setterInputParameter)
                ->addParameters($setterReturnParameter)
            ;

            $getterReturnParameter = new GeneratorParameterModel();
            $getterReturnParameter
                ->setType($parameterType)
                ->setIsReturn(true)
            ;

            $getter
                ->setName(StringHelper::makeGetterName($entityProperty->getName()))
                ->setAccessLevel(AccessLevelEnum::ACCESS_LEVEL_PUBLIC)
                ->setBody(sprintf('return $this->%s;', $entityProperty->getName()))
                ->addParameters($getterReturnParameter)
            ;

            $this->methods[] = $setter;
            $this->methods[] = $getter;
        }
    }

    /**
     * todo
     * @param EntityProperty $entityProperty
     *
     * @return string
     */
    private function prepareParameterType(EntityProperty $entityProperty): string
    {
        $type = '';

        if (!is_null($entityProperty->getType())) {
            $type = $entityProperty->getType();

            switch ($entityProperty->getType()) {
                case PropertyTypeEnum::TYPE_INTEGER:
                    $type = 'int';
                    break;

                case PropertyTypeEnum::TYPE_BOOLEAN:
                    $type = 'bool';
                    break;

                case PropertyTypeEnum::TYPE_NUMBER:
                    $type = 'float';
                    break;
            }

        }

        if ($entityProperty->isForeignKey()) {
            $type = 'int';
        }

        if ($entityProperty->isOneToMany()) {
            $type = 'Doctrine\Common\Collections\ArrayCollection';
        }

        if ($entityProperty->isNullable()) {
            $type = '?' . $type;
        }

        return $type;
    }

    /**
     * @param Entity $entity
     */
    private function prepareInterfaces(Entity $entity): void
    {
        $this->interfaces = array_merge($this->interfaces, $entity->getInterfaces());
    }
}
