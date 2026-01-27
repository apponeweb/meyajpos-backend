<?php

namespace App\Form\Type;

use App\Entity\SalePayment;
use App\Entity\PaymentType;
use App\Entity\Currency;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SalePaymentFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('paymentType', EntityType::class, [
                'class' => PaymentType::class
            ])
            ->add('currency', EntityType::class, [
                'class' => Currency::class
            ])
            ->add('amountReceived', NumberType::class, ['scale' => 2])
            ->add('amountLocalCurrency', NumberType::class, ['scale' => 2])
            ->add('exchangeRateUsed', NumberType::class, ['scale' => 6]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SalePayment::class,
            'csrf_protection' => false
        ]);
    }
}
