<?php

declare(strict_types=1);

namespace App\Form;

use App\Form\Model\SituationsData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * The catalogue of call situations: keep, edit, set aside, add.
 *
 * @extends AbstractType<SituationsData>
 */
final class SituationsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('situations', CollectionType::class, [
            'entry_type' => SituationType::class,
            'allow_add' => true,
            'allow_delete' => true,
            'by_reference' => false,
            'label' => false,
            'prototype_name' => '__situation__',
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => SituationsData::class]);
    }
}
