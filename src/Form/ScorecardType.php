<?php

declare(strict_types=1);

namespace App\Form;

use App\Form\Model\ScorecardData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * The scorecard: criteria with what the coach expects.
 *
 * @extends AbstractType<ScorecardData>
 */
final class ScorecardType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('criteria', CollectionType::class, [
            'entry_type' => CriterionType::class,
            'allow_add' => true,
            'allow_delete' => true,
            'label' => false,
            'prototype_name' => '__criterion__',
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => ScorecardData::class]);
    }
}
