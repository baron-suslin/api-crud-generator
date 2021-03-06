<?php

namespace Requestum\ApiGeneratorBundle\Service\Render\Form\FormPropertyType;

use Requestum\ApiGeneratorBundle\Helper\FormHelper;
use Requestum\ApiGeneratorBundle\Model\Form;
use Requestum\ApiGeneratorBundle\Model\FormProperty;
use Requestum\ApiGeneratorBundle\Service\Render\Form\FormPropertyRenderOutput;

/**
 * Class FormFormPropertyType
 *
 * @package Requestum\ApiGeneratorBundle\Service\Render\Form\FormPropertyType
 */
class FormFormPropertyType extends FormPropertyTypeAbstract
{
    /**
     * @param FormProperty $formProperty
     *
     * @return bool
     */
    public static function isSupport(FormProperty $formProperty): bool
    {
        return
            $formProperty->isForm()
            && empty($formProperty->getType())
        ;
    }

    /**
     * @param FormProperty $formProperty
     *
     * @return FormPropertyRenderOutput
     */
    public function render(FormProperty $formProperty): FormPropertyRenderOutput
    {
        /** @var Form $form */
        $form = $formProperty->getReferencedObject();

        $formPropertyConstraintDto = $this->getNeededConstraints($formProperty);

        return (new FormPropertyRenderOutput())
            ->addUseSections([
                FormHelper::getFormNameSpace($this->bundleName, $form),
            ])
            ->addUseSections($formPropertyConstraintDto->getUses())
            ->setContent($this->wrapProperty(
                $formProperty->getNameCamelCase(),
                FormHelper::getFormClassNameByEntity($form->getEntity()),
                $formPropertyConstraintDto->getContents()
            ))
        ;
    }
}
