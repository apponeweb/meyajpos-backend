<?php

namespace App\License\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\NotBlank;

class LicenseFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('maxBranches', IntegerType::class, [
                'constraints' => [new NotBlank(), new GreaterThan(0)],
            ])
            ->add('maxBarbers', IntegerType::class, [
                'constraints' => [new NotBlank(), new GreaterThan(0)],
            ])
            ->add('startDate', DateType::class, [
                'widget'      => 'single_text',
                'constraints' => [new NotBlank()],
            ])
            ->add('durationDays', IntegerType::class, [
                'constraints' => [new NotBlank(), new GreaterThan(0)],
            ])
            ->add('notes', TextareaType::class, [
                'required' => false,
            ])
            ->add('licenseKey', TextType::class, [
                'required' => false,
            ])
            ->add('maxActivations', IntegerType::class, [
                'constraints' => [new NotBlank(), new GreaterThan(0)],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['csrf_protection' => false]);
    }
}
