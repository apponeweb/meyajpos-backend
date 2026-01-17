<?php

namespace App\Form\Type;

use App\Entity\Sale;
use App\Entity\CashBox;
use App\Entity\User;
use App\Enum\SaleStatus;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Doctrine\ORM\EntityRepository;
use App\Form\Type\SaleDetailFormType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;

class SaleFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('cashBox', EntityType::class, [
                'class' => CashBox::class,
                'choice_label' => 'name',
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('cb')
                        ->where('cb.deletedAt IS NULL')
                        ->andWhere('cb.isActive = :active')
                        ->setParameter('active', true);
                }
            ])
            ->add('folio')
            ->add('user', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'name',
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('u')
                        ->where('u.enabled = :enabled')
                        ->setParameter('enabled', true);
                },
                'placeholder' => 'Seleccione Usuario/Vendedor',
            ])
            ->add('subtotal')
            ->add('totalTax')
            ->add('total')
            ->add('status', EnumType::class, [
                'class' => SaleStatus::class,
                // Esto asegura que se trate como el valor escalar (1, 2, 3)
            ])
            ->add('cancellationReason');
        $builder->add('details', CollectionType::class, [
            'entry_type' => SaleDetailFormType::class,
            'allow_add' => true,
            'by_reference' => false, // Importante para que use addDetail()
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Sale::class,
            'csrf_protection' => false,
            'allow_extra_fields' => true,
        ]);
    }
}
