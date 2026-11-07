<?php

declare(strict_types=1);

namespace App\Form;

use App\Form\Model\ProfileData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * A member's name and description of themself.
 *
 * @extends AbstractType<ProfileData>
 */
final class ProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['label' => 'form.name'])
            ->add('profile', TextareaType::class, ['label' => 'form.profile', 'help' => 'form.profile_help', 'attr' => ['rows' => 6, 'placeholder' => 'form.profile_placeholder']]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => ProfileData::class]);
    }
}
