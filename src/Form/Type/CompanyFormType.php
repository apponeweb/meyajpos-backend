<?php

namespace App\Form\Type;

use App\Entity\Company;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CompanyFormType extends BaseFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        $builder->add('acronym');
        $builder->add('legalName');
        $builder->add('rfc');
        $builder->add('taxAddress');
        $builder->add('phone');
        $builder->add('tagline', TextType::class, ['required' => false]);
        $builder->add('email', TextType::class, ['required' => false]);
        $builder->add('coverImage', TextType::class, ['required' => false]);
        $builder->add('logo', TextType::class, ['required' => false]);
        $builder->add('socialNetworks', TextType::class, ['required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Company::class,
            'csrf_protection' => false,
            'allow_extra_fields' => true,
        ]);
    }
}
