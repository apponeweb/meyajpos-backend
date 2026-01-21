<?php

namespace App\Form\Type;

use App\Entity\Branch;
use App\Entity\Commission;
use App\Entity\Company;
use App\Entity\ServiceType;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CommissionFormType extends BaseFormType
{

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Commission::class,
            'csrf_protection' => false,
        ]);
    }
}
