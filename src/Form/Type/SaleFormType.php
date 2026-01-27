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
            ->add('subtotal')
            ->add('totalTax')
            ->add('total')
            ->add('status', EnumType::class, [
                'class' => SaleStatus::class,
            ])
            ->add('cancellationReason');
        $builder->add('details', CollectionType::class, [
            'entry_type' => SaleDetailFormType::class,
            'allow_add' => true,
            'by_reference' => false, // Importante para que use addDetail()
        ]);
        $builder->add('payments', CollectionType::class, [
            'entry_type' => SalePaymentFormType::class,
            'allow_add' => true,
            'allow_delete' => true,
            'by_reference' => false,
        ]);
        $builder->add('tips', CollectionType::class, [
            'entry_type' => TipFormType::class,
            'allow_add' => true,
            'by_reference' => false, // Esencial para que se vinculen a la Sale
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
