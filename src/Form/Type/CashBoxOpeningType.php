<?php
// src/Form/CashBoxOpeningType.php
namespace App\Form\Type;

use App\Entity\CashBox;
use App\Entity\CashBoxSession;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CashBoxOpeningType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('cashBox', EntityType::class, [
                'class' => CashBox::class,
                'choice_label' => 'name',
                'placeholder' => 'Seleccione una caja'
            ])
            ->add('initialAmount', NumberType::class, [
                'scale' => 2,
                'html5' => true,
                'attr' => ['step' => '0.01']
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CashBoxSession::class,
            'csrf_protection' => false, // Útil para APIs
        ]);
    }
}
