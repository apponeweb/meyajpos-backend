<?php

namespace App\Form\Type;

use App\Entity\Branch;
use App\Entity\Company;
use App\Entity\Currency;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CurrencyFormType extends BaseFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        $builder->add('decimals')
            ->add('symbol')
            ->add('key');
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Currency::class,
            'csrf_protection' => false,
        ]);
    }
}
