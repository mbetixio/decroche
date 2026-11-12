<?php

declare(strict_types=1);

namespace App\Form;

use App\Enum\Difficulty;
use App\Enum\MeetingType;
use App\Form\Model\PrepareData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * A rehearsal of a real meeting, from a prospect file or a call transcript.
 *
 * @extends AbstractType<PrepareData>
 */
final class PrepareType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('fromCall', ChoiceType::class, ['label' => 'form.prepare.source', 'expanded' => true, 'choices' => ['form.prepare.from_file' => false, 'form.prepare.from_call' => true]])
            ->add('text', TextareaType::class, ['label' => 'form.prepare.text', 'empty_data' => '', 'help' => 'form.prepare.text_help', 'attr' => ['rows' => 10, 'placeholder' => 'form.prepare.text_placeholder']])
            ->add('type', EnumType::class, ['class' => MeetingType::class, 'label' => 'form.prepare.type', 'required' => false, 'placeholder' => 'form.prepare.type_any'])
            ->add('difficulty', EnumType::class, ['class' => Difficulty::class, 'label' => 'form.prepare.difficulty'])
            ->add('minutes', IntegerType::class, ['label' => 'form.prepare.minutes', 'attr' => ['min' => 3, 'max' => 60]]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => PrepareData::class]);
    }
}
