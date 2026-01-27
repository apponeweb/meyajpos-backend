<?php
// src/Form/CashBoxClosingType.php
namespace App\Form\Type;

use App\Entity\CashBoxSession;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CashBoxClosingType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // En el cierre usualmente podrías pedir el "monto final" para arqueo
        // Por ahora solo procesamos la acción de cerrar.
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CashBoxSession::class,
            'csrf_protection' => false,
        ]);
    }
}
