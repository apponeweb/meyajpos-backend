<?php

namespace App\Form\Type;

use App\Entity\Branch;
use App\Entity\Company;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Doctrine\ORM\EntityRepository;

class BranchFormType extends BaseFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // 1. Ejecuta la lógica del padre (agrega name y description)
        parent::buildForm($builder, $options);

        // 2. Agrega solo los campos específicos de Branch
        $builder->add('company', EntityType::class, [
            'class' => Company::class,
            'choice_label' => 'id',
            'required' => false,
            'query_builder' => function (EntityRepository $er) {
                return $er->createQueryBuilder('c')
                    ->where('c.deletedAt IS NULL');
            },
            'invalid_message' => 'La compañía seleccionada no es válida.',
        ])
            ->add('acronym')
            ->add('address')
            ->add('phone')
            ->add('image')
            ->add('rating')
            ->add('reviewCount');
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Branch::class,
            'csrf_protection' => false,
            'allow_extra_fields' => true,
        ]);
    }
}
