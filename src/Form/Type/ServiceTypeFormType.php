<?php

namespace App\Form\Type;

use App\Entity\Branch;
use App\Entity\Company;
use App\Entity\ServiceType;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ServiceTypeFormType extends BaseFormType
{

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // 1. Ejecuta la lógica del padre (agrega automáticamente name y description)
        parent::buildForm($builder, $options);
        $builder->add('isCourtesy', CheckboxType::class, ['required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ServiceType::class,
            'csrf_protection' => false,
        ]);
    }
}
