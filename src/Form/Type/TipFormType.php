<?php

namespace App\Form\Type;

use App\Entity\Branch;
use App\Entity\Company;
use App\Entity\PaymentType;
use App\Entity\ServiceType;
use App\Entity\Tip;
use App\Entity\User;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TipFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('salePayment')
            ->add('paymentType', EntityType::class, [
                'class' => PaymentType::class,
                'choice_label' => 'id',
                'required' => false,
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('c')
                        ->where('c.deletedAt IS NULL');
                },
                'invalid_message' => 'El tipo de pago seleccionado no es válida.',
            ])
            ->add('amount') ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Tip::class,
            'csrf_protection' => false,
        ]);
    }
}
