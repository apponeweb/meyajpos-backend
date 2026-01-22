<?php

namespace App\Form\Type;

use App\Entity\CommissionDetail;
use App\Entity\ServiceType;
use App\Enum\ApplicableCommission;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\EnumType;

class CommissionDetailFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('percentage', NumberType::class, [
                'scale' => 4,
            ])
            ->add('applicableCommission', EnumType::class, [
                'class' => ApplicableCommission::class,
            ])
            ->add('serviceType', EntityType::class, [
                'class' => ServiceType::class,
                'choice_label' => 'name', // Cambia por el campo que desees mostrar
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CommissionDetail::class,
            'csrf_protection' => false,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return '';
    }
}
