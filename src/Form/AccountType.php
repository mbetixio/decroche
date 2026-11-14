<?php

declare(strict_types=1);

namespace App\Form;

use App\Form\Data\AccountData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * The first manager's account, created during the onboarding.
 *
 * @extends AbstractType<AccountData>
 */
final class AccountType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['label' => 'form.name', 'attr' => ['autocomplete' => 'name', 'autofocus' => true]])
            ->add('email', EmailType::class, ['label' => 'form.email', 'attr' => ['autocomplete' => 'email']])
            ->add('password', PasswordType::class, ['label' => 'form.password', 'help' => 'form.password_help', 'attr' => ['autocomplete' => 'new-password', 'minlength' => 8]])
            ->add('profile', TextareaType::class, ['label' => 'form.profile', 'help' => 'form.profile_help', 'attr' => ['rows' => 4, 'placeholder' => 'form.profile_placeholder']]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => AccountData::class]);
    }
}
