<?php

namespace App\Form\Type;

use App\Entity\SaleDetail;
use App\Entity\Sale;
use App\Entity\MasterProduct;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Doctrine\ORM\EntityRepository;

class SaleDetailFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('itemLine', TextType::class, [
                'label' => 'Renglón',
                'mapped' => false
            ])
            ->add('product', EntityType::class, [
                'class' => MasterProduct::class,
                'choice_label' => 'name',
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('p')
                        ->where('p.deletedAt IS NULL')
                        ->andWhere('p.isActive = :active')
                        ->setParameter('active', true);
                },
                'placeholder' => 'Seleccione Producto o Servicio',
            ])
            ->add('quantity', NumberType::class, [
                'scale' => 3,
            ])
            ->add('unitPrice', NumberType::class, [
                'scale' => 2,
            ])
            ->add('discount', NumberType::class, [
                'scale' => 2,
                'required' => false,
            ])
            ->add('subtotal', NumberType::class)
            ->add('tax', NumberType::class, [
                'scale' => 2,
            ])
            ->add('total', NumberType::class, [
                'scale' => 2,
            ])
            ->add('serviceProvider', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'name',
                'required' => false,
                'placeholder' => 'Asignar Barbero (Opcional)',
                'query_builder' => function (EntityRepository $er) {
                    // Aquí filtramos por rol de barbero o empleados activos
                    return $er->createQueryBuilder('u')
                        ->where('u.enabled = :active')
                        // Ejemplo: Si usas roles en la DB como strings
                        // ->andWhere('u.roles LIKE :role')
                        // ->setParameter('role', '%ROLE_BARBER%')
                        ->setParameter('active', true)
                        ->orderBy('u.name', 'ASC');
                },
            ])
            ->add('observations', TextType::class, [
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SaleDetail::class,
            'csrf_protection' => false,
            'allow_extra_fields' => true,
        ]);
    }
}
