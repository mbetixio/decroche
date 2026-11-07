<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Member;
use App\Form\Model\MissionData;
use App\Model\Situation;
use App\Repository\CompanyRepository;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * A mission for a member or the whole team.
 *
 * @extends AbstractType<MissionData>
 */
final class MissionType extends AbstractType
{
    public function __construct(
        private readonly CompanyRepository $companies,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $situations = $this->companies->get()->situations();
        $builder
            ->add('assignee', EntityType::class, [
                'class' => Member::class,
                'choice_label' => 'name',
                'required' => false,
                'placeholder' => 'form.mission.whole_team',
                'label' => 'form.mission.assignee',
                'query_builder' => static fn (EntityRepository $r) => $r->createQueryBuilder('m')->orderBy('m.name', 'ASC'),
            ])
            ->add('situation', ChoiceType::class, [
                'choices' => array_combine(array_map(static fn (Situation $s): string => $s->emoji.' '.$s->label, $situations), array_keys($situations)),
                'choice_translation_domain' => false,
                'required' => false,
                'placeholder' => 'form.mission.any_situation',
                'label' => 'form.mission.situation',
            ])
            ->add('calls', IntegerType::class, ['label' => 'form.mission.calls', 'attr' => ['min' => 1, 'max' => 20]])
            ->add('target', IntegerType::class, ['label' => 'form.mission.target', 'attr' => ['min' => 0, 'max' => 100]])
            ->add('dueAt', DateType::class, ['label' => 'form.mission.due', 'required' => false, 'widget' => 'single_text', 'input' => 'datetime_immutable'])
            ->add('note', TextType::class, ['label' => 'form.mission.note', 'required' => false, 'empty_data' => '', 'attr' => ['placeholder' => 'form.mission.note_placeholder']]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => MissionData::class]);
    }
}
