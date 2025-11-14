<?php
// src/Form/ModelTrainingType.php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;

class ModelTrainingType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('train_linear', SubmitType::class, [
                'label' => 'Обучить линейную регрессию',
                'attr' => [
                    'class' => 'btn btn-outline-primary me-2'
                ],
            ])
            ->add('train_random_forest', SubmitType::class, [
                'label' => 'Обучить Random Forest',
                'attr' => [
                    'class' => 'btn btn-outline-success me-2'
                ],
            ])
            ->add('generate_demo_data', SubmitType::class, [
                'label' => 'Сгенерировать демо-данные',
                'attr' => [
                    'class' => 'btn btn-outline-info'
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}
