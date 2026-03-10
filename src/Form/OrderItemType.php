<?php

namespace App\Form;

use App\Entity\OrderItem;
use App\Entity\Product;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class OrderItemType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('product', EntityType::class, [
                'class' => Product::class,
                'choice_label' => function (Product $product) {
                    return $product->getName() . ' - ₱' . number_format($product->getPrice(), 2);
                },
                'placeholder' => 'Select product',
                'required' => true,
                'label' => 'Product',
            ])
            ->add('quantity', IntegerType::class, [
                'required' => true,
                'label' => 'Quantity',
                'attr' => [
                    'min' => 1,
                    'value' => 1,
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => OrderItem::class,
        ]);
    }
}

