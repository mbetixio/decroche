<?php

declare(strict_types=1);

namespace App\Form;

use App\Form\Data\JoinData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Joining the team through an invitation.
 *
 * @extends AbstractType<JoinData>
 */
final class JoinType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['label' => 'form.name', 'attr' => ['autocomplete' => 'name', 'autofocus' => true]])
            ->add('password', PasswordType::class, ['label' => 'form.password', 'help' => 'form.password_help', 'attr' => ['autocomplete' => 'new-password', 'minlength' => 8]]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => JoinData::class]);
    }
}
