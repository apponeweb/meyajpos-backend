<?php

namespace App\Form\Type;

use App\Entity\BarberService;
use App\Entity\MasterProduct;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class BarberServiceFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('barber', EntityType::class, [
                'class' => User::class,
                'required' => true,
            ])
            ->add('product', EntityType::class, [
                'class' => MasterProduct::class,
                'required' => true,
            ])
            ->add('durationOverrideMinutes', IntegerType::class, [
                'required' => false,
            ])
            ->add('isActive', null, [
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => BarberService::class,
            'csrf_protection' => false,
            'allow_extra_fields' => true,
        ]);
    }
}
