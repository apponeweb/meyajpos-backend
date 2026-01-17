<?php

namespace App\Form\Type;

use App\Entity\Branch;
use App\Entity\CashBox;
use App\Entity\Company;
use App\Entity\PaymentType;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CashBoxFormType extends BaseFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // 1. Ejecuta la lógica del padre (agrega name y description)
        parent::buildForm($builder, $options);

        // 2. Agrega solo los campos específicos de Branch
        $builder->add('branch', EntityType::class, [
            'class' => Branch::class,
            'choice_label' => 'id',
            'required' => false,
            'query_builder' => function (EntityRepository $er) {
                return $er->createQueryBuilder('c')
                    ->where('c.deletedAt IS NULL');
            },
            'invalid_message' => 'La sucursal seleccionada no es válida.',
        ])
            ->add('currentFolio')
            ->add('ticketSerie');
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CashBox::class,
            'csrf_protection' => false,
        ]);
    }
}
