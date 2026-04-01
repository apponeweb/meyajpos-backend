<?php

namespace App\Form\Type;

use App\Entity\Branch;
use App\Entity\Review;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class ReviewFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('customerName', TextType::class, [
                'label' => 'Nombre del cliente',
                'required' => true,
                'constraints' => [
                    new Assert\NotBlank(['message' => 'El nombre del cliente es requerido']),
                    new Assert\Length([
                        'max' => 100,
                        'maxMessage' => 'El nombre no puede exceder {{ limit }} caracteres'
                    ]),
                ],
            ])
            ->add('rating', IntegerType::class, [
                'label' => 'Calificación',
                'required' => true,
                'constraints' => [
                    new Assert\NotBlank(['message' => 'La calificación es requerida']),
                    new Assert\Range([
                        'min' => 1,
                        'max' => 5,
                        'notInRangeMessage' => 'La calificación debe estar entre {{ min }} y {{ max }}'
                    ]),
                ],
            ])
            ->add('comment', TextareaType::class, [
                'label' => 'Comentario',
                'required' => true,
                'constraints' => [
                    new Assert\NotBlank(['message' => 'El comentario es requerido']),
                ],
            ])
            ->add('branch', EntityType::class, [
                'class' => Branch::class,
                'required' => false,
                'query_builder' => function (\Doctrine\ORM\EntityRepository $er) {
                    return $er->createQueryBuilder('b')
                        ->where('b.deletedAt IS NULL')
                        ->andWhere('b.isActive = true');
                },
            ])
            ->add('barber', EntityType::class, [
                'class' => User::class,
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Review::class,
            'csrf_protection' => false,
            'allow_extra_fields' => true,
        ]);
    }
}
