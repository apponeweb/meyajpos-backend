<?php

namespace App\Form\Type;

use App\Entity\CashBoxMovement;
use App\Enum\CashMovementConcept;
use App\Enum\CashMovementType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CashBoxMovementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('type', EnumType::class, ['class' => CashMovementType::class])
            ->add('concept', EnumType::class, ['class' => CashMovementConcept::class])
            ->add('amount', NumberType::class, ['scale' => 2])
            ->add('change', NumberType::class, ['scale' => 2])
            ->add('description', TextType::class, ['required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CashBoxMovement::class,
            'csrf_protection' => false,
        ]);
    }
}
