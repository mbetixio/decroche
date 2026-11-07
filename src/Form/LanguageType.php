<?php

declare(strict_types=1);

namespace App\Form;

use App\Enum\Language;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * The working language, picked on the first onboarding screen.
 *
 * @extends AbstractType<array{language: Language}>
 */
final class LanguageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('language', EnumType::class, [
            'class' => Language::class,
            'expanded' => true,
            'label' => false,
            'choice_label' => static fn (Language $l): string => $l->nativeName(),
            'choice_translation_domain' => false,
        ]);
    }
}
