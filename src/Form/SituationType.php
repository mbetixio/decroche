<?php

declare(strict_types=1);

namespace App\Form;

use App\Enum\CallDirection;
use App\Enum\SituationFamily;
use App\Form\Model\SituationData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * One call situation of the catalogue.
 *
 * @extends AbstractType<SituationData>
 */
final class SituationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('key', HiddenType::class)
            ->add('keep', CheckboxType::class, ['label' => 'form.situation.keep', 'required' => false])
            ->add('emoji', TextType::class, ['label' => 'form.situation.emoji', 'attr' => ['maxlength' => 4, 'class' => 'emoji-input']])
            ->add('label', TextType::class, ['label' => 'form.situation.label'])
            ->add('family', EnumType::class, ['class' => SituationFamily::class, 'label' => 'form.situation.family'])
            ->add('direction', EnumType::class, ['class' => CallDirection::class, 'label' => 'form.situation.direction'])
            ->add('material', TextareaType::class, ['label' => 'form.situation.material', 'required' => false, 'empty_data' => '', 'attr' => ['rows' => 2]])
            ->add('pitch', TextareaType::class, ['label' => 'form.situation.pitch', 'attr' => ['rows' => 3]])
            ->add('objectives', TextareaType::class, ['label' => 'form.situation.objectives', 'help' => 'form.one_per_line', 'attr' => ['rows' => 3]]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SituationData::class,
            // A situation set aside is not checked: only the kept ones must be complete.
            'validation_groups' => static fn (FormInterface $form): array => ['Default'] + ($form->getData() instanceof SituationData && $form->getData()->keep ? [1 => 'kept'] : []),
        ]);
    }
}
