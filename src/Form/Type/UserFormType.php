<?php

namespace App\Form\Type;

use App\Entity\Branch;
use App\Entity\Commission;
use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;

class UserFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name')
            ->add('lastName')
            ->add('phone')
            ->add('email')
            ->add('password')
            ->add('roles', ChoiceType::class, [
                'choices' => [
                    'Cajero' => 'ROLE_CASHIER',
                    'Administrador' => 'ROLE_ADMIN'
                ],
                'by_reference' => false,
                'multiple' => true,
            ])
            ->add('enabled')
            ->add('barberSn')
            ->add('photoUrl')
            ->add('commission', EntityType::class, [
                'class' => Commission::class,
                'choice_label' => 'id',
                'required' => false,
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('b')
                        ->where('b.deletedAt IS NULL');
                },
                'invalid_message' => 'La comisión seleccionada no es válida.',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'csrf_protection' => false
        ]);
    }


    public function getBlockPrefix(): string
    {
        return '';
    }

    public function getName(): string
    {
        return '';
    }

}
