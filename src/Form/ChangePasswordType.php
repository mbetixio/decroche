<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Security\Core\Validator\Constraints\UserPassword;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Changing one's password: the current one, then the new one.
 *
 * @extends AbstractType<array{current: string, password: string}>
 */
final class ChangePasswordType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('current', PasswordType::class, ['label' => 'form.current_password', 'constraints' => [new UserPassword(message: 'account.error.wrong_password')], 'attr' => ['autocomplete' => 'current-password']])
            ->add('password', PasswordType::class, ['label' => 'form.new_password', 'help' => 'form.password_help', 'constraints' => [new Assert\NotBlank(), new Assert\Length(min: 8, max: 4096)], 'attr' => ['autocomplete' => 'new-password']]);
    }
}
