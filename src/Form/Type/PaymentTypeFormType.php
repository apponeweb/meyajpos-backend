<?php

namespace App\Form\Type;

use App\Entity\Branch;
use App\Entity\Company;
use App\Entity\PaymentType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PaymentTypeFormType extends BaseFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        $builder->add('isCash')
            ->add('referenceRequired') ;
    }
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PaymentType::class,
            'csrf_protection' => false,
        ]);
    }
}
