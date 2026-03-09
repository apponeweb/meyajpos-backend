<?php

namespace App\Form\Type;

use App\Entity\Branch;
use App\Entity\BranchHour;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class BranchHourFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('branch', EntityType::class, [
                'class' => Branch::class,
                'required' => true,
            ])
            ->add('dayOfWeek', IntegerType::class, [
                'required' => true,
            ])
            ->add('openTime', TimeType::class, [
                'widget' => 'single_text',
                'required' => true,
            ])
            ->add('closeTime', TimeType::class, [
                'widget' => 'single_text',
                'required' => true,
            ])
            ->add('validFrom', DateType::class, [
                'widget' => 'single_text',
                'required' => true,
            ])
            ->add('validTo', DateType::class, [
                'widget' => 'single_text',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => BranchHour::class,
            'csrf_protection' => false,
            'allow_extra_fields' => true,
        ]);
    }
}
