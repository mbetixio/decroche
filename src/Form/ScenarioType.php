<?php

declare(strict_types=1);

namespace App\Form;

use App\Enum\BriefSection;
use App\Enum\Difficulty;
use App\Enum\MeetingType;
use App\Form\Model\ScenarioData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * A library scenario: what the seller knows, the persona, and the hidden brief.
 *
 * @extends AbstractType<ScenarioData>
 */
final class ScenarioType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, ['label' => 'form.scenario.title'])
            ->add('type', EnumType::class, ['class' => MeetingType::class, 'label' => 'form.scenario.type'])
            ->add('difficulty', EnumType::class, ['class' => Difficulty::class, 'label' => 'form.scenario.difficulty'])
            ->add('minutes', IntegerType::class, ['label' => 'form.scenario.minutes'])
            ->add('context', TextareaType::class, ['label' => 'form.scenario.context', 'attr' => ['rows' => 3]])
            ->add('goal', TextareaType::class, ['label' => 'form.scenario.goal', 'attr' => ['rows' => 2]])
            ->add('personaName', TextType::class, ['label' => 'form.scenario.persona_name'])
            ->add('personaRole', TextType::class, ['label' => 'form.scenario.persona_role', 'required' => false, 'empty_data' => ''])
            ->add('personaCompany', TextType::class, ['label' => 'form.scenario.persona_company', 'required' => false, 'empty_data' => ''])
            ->add('personaPersonality', TextareaType::class, ['label' => 'form.scenario.persona_personality', 'required' => false, 'empty_data' => '', 'attr' => ['rows' => 2]])
            ->add('opening', TextareaType::class, ['label' => 'form.scenario.opening', 'attr' => ['rows' => 2]]);

        $brief = $builder->create('brief', FormType::class, ['label' => 'form.scenario.brief']);
        foreach (BriefSection::cases() as $section) {
            $brief->add($section->value, TextareaType::class, ['label' => $section, 'required' => BriefSection::Pain === $section, 'empty_data' => '', 'attr' => ['rows' => 2]]);
        }
        $builder->add($brief);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => ScenarioData::class]);
    }
}
