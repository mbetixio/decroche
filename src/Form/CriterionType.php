<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * One criterion of the scorecard.
 *
 * @extends AbstractType<array{label: string, hint: string}>
 */
final class CriterionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('label', TextType::class, ['label' => 'form.criterion.label', 'empty_data' => ''])
            ->add('hint', TextType::class, ['label' => 'form.criterion.hint', 'required' => false, 'empty_data' => '']);
    }
}
