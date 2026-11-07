<?php

declare(strict_types=1);

namespace App\Form;

use App\Form\Model\CompanyData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * The company's identity: name, website, description.
 *
 * @extends AbstractType<CompanyData>
 */
final class CompanyType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['label' => 'form.company_name', 'attr' => ['autocomplete' => 'organization', 'autofocus' => true]])
            ->add('website', TextType::class, ['label' => 'form.website', 'required' => false, 'help' => 'form.website_help', 'attr' => ['placeholder' => 'example.com', 'inputmode' => 'url']])
            ->add('about', TextareaType::class, ['label' => 'form.about', 'required' => false, 'empty_data' => '', 'help' => 'form.about_help', 'attr' => ['rows' => 5, 'placeholder' => 'form.about_placeholder']]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => CompanyData::class]);
    }
}
