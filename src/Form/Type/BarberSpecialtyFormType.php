<?php

namespace App\Form\Type;

use App\Entity\BarberSpecialty;
use App\Entity\Specialty;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class BarberSpecialtyFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('barber', EntityType::class, [
                'class' => User::class,
                'required' => true,
            ])
            ->add('specialty', EntityType::class, [
                'class' => Specialty::class,
                'required' => true,
            ])
            ->add('isActive')
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => BarberSpecialty::class,
            'csrf_protection' => false,
            'allow_extra_fields' => true,
        ]);
    }
}
