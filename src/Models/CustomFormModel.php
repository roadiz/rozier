<?php

declare(strict_types=1);

namespace Themes\Rozier\Models;

use RZ\Roadiz\Core\Entities\CustomForm;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Translation\Translator;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @package Themes\Rozier\Models
 */
final class CustomFormModel implements ModelInterface
{
    private CustomForm $customForm;
    private UrlGeneratorInterface $urlGenerator;
    private TranslatorInterface $translator;

    /**
     * @param CustomForm $customForm
     * @param UrlGeneratorInterface $urlGenerator
     */
    public function __construct(CustomForm $customForm, UrlGeneratorInterface $urlGenerator, TranslatorInterface $translator)
    {
        $this->customForm = $customForm;
        $this->urlGenerator = $urlGenerator;
        $this->translator = $translator;
    }

    public function toArray()
    {
        if ($this->translator instanceof Translator) {
            $countFields = strip_tags($this->translator->transChoice(
                '{0} no.customFormField|{1} 1.customFormField|]1,Inf] %count%.customFormFields',
                $this->customForm->getFields()->count(),
                [
                    '%count%' => $this->customForm->getFields()->count()
                ]
            ));
        } else {
            $countFields = strip_tags($this->translator->trans(
                '{0} no.customFormField|{1} 1.customFormField|]1,Inf] %count%.customFormFields',
                [
                    '%count%' => $this->customForm->getFields()->count()
                ]
            ));
        }

        return [
            'id' => $this->customForm->getId(),
            'name' => $this->customForm->getDisplayName(),
            'countFields' => $countFields,
            'color' => $this->customForm->getColor(),
            'customFormsEditPage' => $this->urlGenerator->generate('customFormsEditPage', [
                'id' => $this->customForm->getId()
            ]),
        ];
    }
}
