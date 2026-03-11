<?php

namespace App\Form\Type;

use App\Entity\BarberProfile;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class BarberProfileFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('user', EntityType::class, [
                'class' => User::class,
                'required' => true,
            ])
            ->add('bio', TextareaType::class, [
                'required' => false,
            ])
            ->add('avgRating', NumberType::class, [
                'required' => false,
            ])
            ->add('ratingCount', IntegerType::class, [
                'required' => false,
            ])
            ->add('slotMinutes', IntegerType::class, [
                'required' => false,
            ])
            ->add('classification', TextType::class, [
                'required' => false,
            ])
            ->add('experience', TextType::class, [
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => BarberProfile::class,
            'csrf_protection' => false,
            'allow_extra_fields' => true,
        ]);
    }
}
