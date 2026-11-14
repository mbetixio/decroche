<?php

declare(strict_types=1);

namespace App\Form;

use App\Form\Data\OfferData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * The offer sheet and the typical clients.
 *
 * @extends AbstractType<OfferData>
 */
final class OfferType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('offer', TextareaType::class, ['label' => 'form.offer', 'help' => 'form.offer_help', 'attr' => ['rows' => 22, 'class' => 'offer-editor']])
            ->add('sectors', TextareaType::class, ['label' => 'form.sectors', 'required' => false, 'empty_data' => '', 'help' => 'form.sectors_help', 'attr' => ['rows' => 5]]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => OfferData::class]);
    }
}
