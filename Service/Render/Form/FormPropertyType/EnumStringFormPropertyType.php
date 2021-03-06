<?php

namespace Requestum\ApiGeneratorBundle\Service\Render\Form\FormPropertyType;

use Requestum\ApiGeneratorBundle\Model\Enum\PropertyTypeEnum;
use Requestum\ApiGeneratorBundle\Model\FormProperty;
use Requestum\ApiGeneratorBundle\Service\Render\Form\FormPropertyRenderOutput;

/**
 * Class EnumStringFormPropertyType
 *
 * @package Requestum\ApiGeneratorBundle\Service\Render\Form\FormPropertyType
 */
class EnumStringFormPropertyType extends FormPropertyTypeAbstract
{
    /**
     * @param FormProperty $formProperty
     *
     * @return bool
     */
    public static function isSupport(FormProperty $formProperty): bool
    {
        return
            $formProperty->getType() === PropertyTypeEnum::TYPE_STRING
            && !empty($formProperty->getEnum())
        ;
    }

    /**
     * @param FormProperty $formProperty
     *
     * @return FormPropertyRenderOutput
     */
    public function render(FormProperty $formProperty): FormPropertyRenderOutput
    {
        $formPropertyConstraintDto = $this->getNeededConstraints($formProperty);

        $enum = $formProperty->getEnum();
        $enum = "'" . implode("', '", $enum) . "',";

        $optionsContent = <<<EOF
    'choices' => [
            {$enum}
        ],
EOF     ;

        return (new FormPropertyRenderOutput())
            ->addUseSections([
                'Symfony\Component\Form\Extension\Core\Type\ChoiceType',
            ])
            ->addUseSections($formPropertyConstraintDto->getUses())
            ->setContent(
                $this->wrapProperty(
                    $formProperty->getNameCamelCase(),
                    'ChoiceType',
                    $formPropertyConstraintDto->getContents(),
                    $optionsContent
                )
            )
        ;
    }
}
