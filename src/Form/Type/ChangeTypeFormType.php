<?php

namespace App\Form\Type;

use App\Entity\Branch;
use App\Entity\ChangeType;
use App\Entity\Company;
use App\Entity\Currency;
use App\Entity\ServiceType;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ChangeTypeFormType extends BaseFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        $builder
            ->add('currencyOrigin', EntityType::class, [
                'class' => Currency::class,
                'choice_label' => 'id',
                'required' => false,
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('c')
                        ->where('c.deletedAt IS NULL');
                },
                'invalid_message' => 'La moneda de inicio seleccionada no es válida.',
            ])
            ->add('currencyDestination', EntityType::class, [
                'class' => Currency::class,
                'choice_label' => 'id',
                'required' => false,
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('c')
                        ->where('c.deletedAt IS NULL');
                },
                'invalid_message' => 'La moneda de destino seleccionada no es válida.',
            ])
            ->add('changeType')
            ->add('taxDate')
            ->add('source');
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ChangeType::class,
            'csrf_protection' => false,
        ]);
    }
}
