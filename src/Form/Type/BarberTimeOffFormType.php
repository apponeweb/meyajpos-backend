<?php

namespace App\Form\Type;

use App\Entity\BarberTimeOff;
use App\Entity\User;
use App\Entity\Branch;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class BarberTimeOffFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('barber', EntityType::class, [
                'class' => User::class,
                'required' => true,
            ])
            ->add('startAtLocal', DateTimeType::class, [
                'widget' => 'single_text',
                'required' => true,
            ])
            ->add('endAtLocal', DateTimeType::class, [
                'widget' => 'single_text',
                'required' => true,
            ])
            ->add('reason', TextType::class, [
                'required' => false,
            ])
            ->add('branch', EntityType::class, [
                'class' => Branch::class,
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => BarberTimeOff::class,
            'csrf_protection' => false,
            'allow_extra_fields' => true,
        ]);
    }
}
