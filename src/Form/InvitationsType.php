<?php

declare(strict_types=1);

namespace App\Form;

use App\Enum\MemberRole;
use App\Form\Model\InvitationsData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * People to invite to the team.
 *
 * @extends AbstractType<InvitationsData>
 */
final class InvitationsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('emails', TextareaType::class, ['label' => 'form.invite_emails', 'required' => false, 'empty_data' => '', 'help' => 'form.invite_emails_help', 'attr' => ['rows' => 4, 'placeholder' => "alex@example.com\ncamille@example.com"]])
            ->add('role', EnumType::class, ['class' => MemberRole::class, 'label' => 'form.role', 'expanded' => true]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => InvitationsData::class]);
    }
}
