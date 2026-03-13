<?php

namespace App\Form\Type;

use App\Entity\MasterProduct;
use App\Entity\ServiceType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Doctrine\ORM\EntityRepository;

class MasterProductFormType extends BaseFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // 1. Ejecuta la lógica del padre (agrega automáticamente name y description)
        parent::buildForm($builder, $options);

        // 2. Agrega los campos específicos de MasterProduct
        $builder
            ->add('sku')
            ->add('barcode')
            ->add('price')
            ->add('uom')
            ->add('isInventoriable')
            ->add('serviceType', EntityType::class, [
                'class' => ServiceType::class,
                'choice_label' => 'name', // Usamos el nombre del ServiceType
                'required' => false,
                'placeholder' => 'Seleccione un tipo de servicio...',
                'query_builder' => function (EntityRepository $er) {
                    // Filtramos para no mostrar tipos de servicio eliminados lógicamente
                    return $er->createQueryBuilder('s')
                        ->where('s.deletedAt IS NULL')
                        ->andWhere('s.isActive = :active')
                        ->setParameter('active', true)
                        ->orderBy('s.name', 'ASC');
                },
                'invalid_message' => 'El tipo de servicio seleccionado no es válido.',
            ])
            ->add('image');
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => MasterProduct::class,
            'csrf_protection' => false, // Útil si estás trabajando con APIs
            'allow_extra_fields' => true,
        ]);
    }
}
