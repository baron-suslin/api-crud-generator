<?php

namespace Requestum\ApiGeneratorBundle\Service\Builder;

use Requestum\ApiGeneratorBundle\Exception\PrimaryException;
use Requestum\ApiGeneratorBundle\Exception\ReferencedColumnException;
use Requestum\ApiGeneratorBundle\Helper\StringHelper;
use Requestum\ApiGeneratorBundle\Model\BaseAbstractCollection;
use Requestum\ApiGeneratorBundle\Model\Entity;
use Requestum\ApiGeneratorBundle\Model\EntityCollection;
use Requestum\ApiGeneratorBundle\Model\EntityProperty;
use Requestum\ApiGeneratorBundle\Model\PropertyTypeEnum;
use Requestum\ApiGeneratorBundle\Service\Config;


/**
 * Class EntityBuilder
 *
 * @package Requestum\ApiGeneratorBundle\Service\Builder
 */
class EntityBuilder extends AbstractBuilder
{

    /**
     * EntityBuilder constructor.
     *
     * @param Config $config
     */
    public function __construct(Config $config)
    {
        parent::__construct($config);

        $this->collection = new EntityCollection($config);
    }

    /**
     * @param array $openApiSchema
     *
     * @return BaseAbstractCollection
     *
     * @throws \Exception
     */
    public function build(array $openApiSchema, ?BaseAbstractCollection $relatedCollection = null): BaseAbstractCollection
    {
        if (empty($openApiSchema['components']['schemas'])) {
            return $this->collection;
        }

        foreach ($openApiSchema['components']['schemas'] as $objectName => $objectData) {

            if (null !== ($name = StringHelper::getEntityNameFromObjectName($objectName))) {
                $required = !empty($objectData['required']) ? array_map(['\Requestum\ApiGeneratorBundle\Helper\StringHelper', 'camelCaseToSnakeCaseName'], $objectData['required']): [];
                $primary = !empty($objectData['x-primary-key']) ? array_map(['\Requestum\ApiGeneratorBundle\Helper\StringHelper', 'camelCaseToSnakeCaseName'], $objectData['x-primary-key']): [];

                $entity = new Entity();
                $entity
                    ->setName($name)
                    ->setOriginObjectName($objectName)
                    ->setDescription(!empty($objectData['description']) ? $objectData['description']: null)
                    ->setTableName(
                        StringHelper::camelCaseToSnakeCaseName($name)
                    )
                    ->setProperties(
                        $this->buildProperties($objectData['properties'], $required, $primary)
                    )
                ;

                $this->collection->addElement($entity);
            }
        }

        $this->processRelations();

        return $this->collection;
    }

    /**
     * @param array $propertiesData
     * @param array $required
     * @param array $primary
     *
     * @return array
     *
     * @throws \Exception
     */
    private function buildProperties(array $propertiesData, array $required, array $primary): array
    {
        $properties = [];

        foreach ($propertiesData as $field => $data) {
            $property = new EntityProperty();
            $property
                ->setName($field)
                ->setDatabasePropertyName(
                    StringHelper::camelCaseToSnakeCaseName($field)
                )
                ->setDescription(!empty($data['description']) ? $data['description']: null)
                ->setRequired(in_array($property->getDatabasePropertyName(), $required))
                ->setPrimary(in_array($property->getDatabasePropertyName(), $primary))
            ;

            if (!empty($data['type'])) {
                $property->setType($data['type']);
            }

            if (!empty($data['format'])) {
                $property->setFormat($data['format']);
            }

            if (!empty($data['items']['type'])) {
                $property->setItemsType($data['items']['type']);
            }

            if (!empty($data['enum']) && is_array($data['enum'])) {
                $property->setEnum(
                    array_map(['\Requestum\ApiGeneratorBundle\Helper\StringHelper', 'camelCaseToSnakeCaseName'], $data['enum'])
                );
            }

            if (!empty($data['$ref'])) {
                $property->setReferencedLink(
                    StringHelper::getReferencedSchemaObjectName($data['$ref'])
                );
            }

            if (!empty($data['items']['$ref'])) {
                $property->setReferencedLink(
                    StringHelper::getReferencedSchemaObjectName($data['items']['$ref'])
                );
            }

            if (!empty($data['x-backref'])) {
                $property->setBackRef($data['x-backref']);
            }

            if (isset($data['x-uselist'])) {
                $property->setUseList($data['x-uselist']);
            }

            // applies only for string type
            if (!empty($data['minLength'])) {
                $property->setMinLength($data['minLength']);
            }

            // applies only for string type
            if (!empty($data['maxLength'])) {
                $property->setMaxLength($data['maxLength']);
            }

            // applies only for integer or number types
            if (!empty($data['minimum'])) {
                $property->setMinimum($data['minimum']);
            }

            // applies only for integer or number types
            if (!empty($data['maximum'])) {
                $property->setMaximum($data['maximum']);
            }

            // applies only for integer or number types
            if (!empty($data['minItems'])) {
                $property->setMinItems($data['minItems']);
            }

            // applies only for integer or number types
            if (!empty($data['maxItems'])) {
                $property->setMaxItems($data['maxItems']);
            }

            // applies only for string type
            if (!empty($data['pattern'])) {
                $property->setPattern($data['pattern']);
            }

            if (isset($data['nullable'])) {
                $property->setNullable($data['nullable']);
            }

            if (isset($data['readOnly'])) {
                $property->setReadOnly($data['readOnly']);
            }

            $properties[] = $property;
        }

        return $properties;
    }

    private function processRelations()
    {
        /**
         * @var string $entityName
         * @var Entity $entity
         */
        foreach ($this->collection->getElements() as $entityName => $entity) {
            /** @var EntityProperty $property */
            foreach ($entity->filterReferencedLinkProperties() as $property) {
                if (is_null($property->getReferencedLink())) {
                    continue;
                }

                $targetEntity = $this->collection->findElement(StringHelper::getEntityNameFromObjectName($property->getReferencedLink()));
                if (is_null($targetEntity)) {
                    continue;
                }

                $this->detectOneToOneRelation($property, $targetEntity);
                if ($property->isOneToOne()) {
                    continue;
                }

                $this->detectManyToOneRelation($property, $targetEntity);
                if ($property->isManyToOne()) {
                    continue;
                }

                $this->detectOneToManyRelation($property, $targetEntity);
                if ($property->isOneToMany()) {
                    continue;
                }

                $this->detectManyToManyRelation($property, $targetEntity);
            }
        }
    }

    /**
     * @param EntityProperty $property
     * @param Entity $targetEntity
     *
     * @throws PrimaryException
     * @throws ReferencedColumnException
     */
    private function detectManyToOneRelation(EntityProperty $property, Entity $targetEntity)
    {
        if ($property->checkType(PropertyTypeEnum::TYPE_INTEGER) || is_null($property->getType()) && is_null($property->isUseList())) {

            $property
                ->setReferencedColumn(
                    $this->getPrimaryKey($targetEntity)
                )
                ->setManyToOne(true)
                ->setForeignKey(true)
            ;

            $relatedProperty = $targetEntity->getRelatedProperty($property->getEntity()->getOriginObjectName());
            if ($relatedProperty) {
                if (!$relatedProperty->checkType(PropertyTypeEnum::TYPE_ARRAY)) {
                    throw new ReferencedColumnException(
                        sprintf(
                            'The column % has to be type %s, type %s is given',
                            $relatedProperty->getName(),
                            PropertyTypeEnum::TYPE_ARRAY,
                            $relatedProperty->getType(),
                        )
                    );
                }

                $referencedColumn = $relatedProperty->getReferencedColumn();
                if ($this->checkReferencedColumn($referencedColumn, $property)) {
                    return;
                }

                $relatedProperty
                    ->setReferencedColumn($property)
                    ->setOneToMany(true)
                    ->setBackRefColumn(true)
                ;
            }
        }
    }

    /**
     * @param EntityProperty $property
     * @param Entity $targetEntity
     *
     * @throws PrimaryException
     * @throws ReferencedColumnException
     */
    private function detectOneToManyRelation(EntityProperty $property, Entity $targetEntity)
    {
        if ($property->checkType(PropertyTypeEnum::TYPE_ARRAY)) {
            $relatedProperty = $targetEntity->getRelatedProperty($property->getEntity()->getOriginObjectName());
            if ($relatedProperty) {
                if ($relatedProperty->checkType(PropertyTypeEnum::TYPE_INTEGER) || is_null($relatedProperty->getType())) {
                    $referencedColumn = $relatedProperty->getReferencedColumn();

                    $primaryKey = $this->getPrimaryKey($property->getEntity());
                    if ($this->checkReferencedColumn($referencedColumn, $primaryKey)) {
                        return;
                    }

                    $relatedProperty
                        ->setReferencedColumn($primaryKey)
                        ->setManyToOne(true)
                        ->setForeignKey(true)
                    ;

                    $property
                        ->setReferencedColumn($relatedProperty)
                        ->setOneToMany(true)
                        ->setBackRefColumn(true)
                    ;
                }
            }
        }
    }

    /**
     * @param EntityProperty $property
     * @param Entity $targetEntity
     *
     * @throws PrimaryException
     * @throws ReferencedColumnException
     */
    private function detectOneToOneRelation(EntityProperty $property, Entity $targetEntity)
    {
        if (($property->checkType(PropertyTypeEnum::TYPE_INTEGER) || is_null($property->getType())) && ($property->isUseList() === false)) {

            $property
                ->setOneToOne(true)
                ->setForeignKey(true)
                ->setReferencedColumn(
                    $this->getPrimaryKey($targetEntity)
                )
            ;

            if (is_null($property->getBackRef())) {
                return;
            }


            $relatedProperty = $targetEntity->getProperyByName($property->getBackRef());
            if (!$relatedProperty) {
                throw new ReferencedColumnException(
                    sprintf(
                        'Couldn\'t find a referenced column %s in an entity %s',
                        $property->getBackRef(),
                        $targetEntity->getName(),
                    )
                );
            }

            $referencedColumn = $relatedProperty->getReferencedColumn();
            if ($this->checkReferencedColumn($referencedColumn, $property)) {
                return;
            }

            if (!is_null($property->getBackRef()) && !is_null($relatedProperty->getBackRef())) {
                throw new ReferencedColumnException('The back referenced column has to be only from one side');
            }

            $relatedProperty
                ->setReferencedColumn($property)
                ->setOneToOne(true)
                ->setBackRefColumn(true)
            ;
        }
    }

    /**
     * @param EntityProperty $property
     * @param Entity         $targetEntity
     */
    private function detectManyToManyRelation(EntityProperty $property, Entity $targetEntity)
    {
        if ($property->checkType(PropertyTypeEnum::TYPE_ARRAY)) {
            $relatedProperty = $targetEntity->getRelatedProperty($property->getEntity()->getOriginObjectName());
            if ($relatedProperty) {
                $referencedColumn = $relatedProperty->getReferencedColumn();
                if ($this->checkReferencedColumn($referencedColumn, $this->getPrimaryKey($property->getEntity()))) {
                    return;
                }

                if ($relatedProperty->checkType(PropertyTypeEnum::TYPE_ARRAY)) {
                    $relatedProperty
                        ->setReferencedColumn(
                            $this->getPrimaryKey($property->getEntity())
                        )
                        ->setManyToMany(true)
                    ;

                    $property
                        ->setReferencedColumn(
                            $this->getPrimaryKey($relatedProperty->getEntity())
                        )
                        ->setManyToMany(true)
                    ;
                }
            }
        }
    }

    /**
     * @param Entity $entity
     *
     * @return EntityProperty
     *
     * @throws PrimaryException
     */
    private function getPrimaryKey(Entity $entity): EntityProperty
    {
        $primaryKeys = [...$entity->getPrimaryColumns()];
        if (count($primaryKeys) === 0) {
            throw new PrimaryException(sprintf('The entity "%s" doesn\'t have any primary key', $entity->getName()));
        }

        if (count($primaryKeys) > 1) {
            throw new PrimaryException(sprintf('The entity "%s" has more than one primary key', $entity->getName()));
        }

        return reset($primaryKeys);
    }

    /**
     * @param EntityProperty|null $referencedColumn
     * @param EntityProperty      $property
     *
     * @return bool
     * @throws ReferencedColumnException
     */
    private function checkReferencedColumn(?EntityProperty $referencedColumn, EntityProperty $property): bool
    {
        if ($referencedColumn) {
            if ($referencedColumn !== $property) {
                throw new ReferencedColumnException(
                    sprintf(
                        'Wrong referenced column %s::%s. Expected %s::%s',
                        $property->getEntity()->getName(),
                        $property->getName(),
                        $referencedColumn->getEntity()->getName(),
                        $referencedColumn->getName(),
                    )
                );
            }

            return true;
        }

        return false;
    }
}