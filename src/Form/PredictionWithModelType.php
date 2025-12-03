<?php
// src/Form/PredictionWithModelType.php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Validator\Constraints as Assert;

class PredictionWithModelType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('area', NumberType::class, [
                'label' => 'Площадь помещения (м²)',
                'required' => true,
                'attr' => [
                    'min' => 10,
                    'max' => 500,
                    'step' => 0.1,
                    'placeholder' => 'Введите площадь помещения',
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Площадь помещения обязательна для заполнения',
                    ]),
                    new Assert\Range([
                        'min' => 10,
                        'max' => 500,
                        'notInRangeMessage' => 'Площадь должна быть между {{ min }} и {{ max }} м²',
                    ]),
                ],
                'html5' => true,
            ])
            ->add('residents', NumberType::class, [
                'label' => 'Количество жильцов',
                'required' => true,
                'attr' => [
                    'min' => 1,
                    'max' => 20,
                    'placeholder' => 'Введите количество жильцов',
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Количество жильцов обязательно для заполнения',
                    ]),
                    new Assert\Range([
                        'min' => 1,
                        'max' => 20,
                        'notInRangeMessage' => 'Количество жильцов должно быть между {{ min }} и {{ max }}',
                    ]),
                ],
                'html5' => true,
            ])
            ->add('season', ChoiceType::class, [
                'label' => 'Сезон',
                'required' => true,
                'choices' => [
                    'Зима' => 'winter',
                    'Весна' => 'spring',
                    'Лето' => 'summer',
                    'Осень' => 'autumn',
                ],
                'attr' => [
                    'class' => 'form-select'
                ],
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Выберите сезон',
                    ]),
                ],
                'placeholder' => 'Выберите сезон',
            ])
            ->add('temperature', NumberType::class, [
                'label' => 'Ожидаемая температура (°C)',
                'required' => true,
                'attr' => [
                    'min' => -50,
                    'max' => 50,
                    'step' => 0.1,
                    'placeholder' => 'Введите ожидаемую температуру',
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Температура обязательна для заполнения',
                    ]),
                    new Assert\Range([
                        'min' => -50,
                        'max' => 50,
                        'notInRangeMessage' => 'Температура должна быть между {{ min }} и {{ max }}°C',
                    ]),
                ],
                'html5' => true,
            ])
            ->add('model_type', ChoiceType::class, [
                'label' => 'Модель прогнозирования',
                'required' => true,
                'choices' => [
                    'Линейная регрессия' => 'linear_regression',
                    'Support Vector Regression (SVR)' => 'support_vector_regression',
                    'Автовыбор (рекомендуется)' => 'auto',
                ],
                'attr' => [
                    'class' => 'form-select'
                ],
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Выберите модель прогнозирования',
                    ]),
                ],
                'data' => 'auto',
                'help' => 'Автовыбор подберет оптимальную модель на основе данных'
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}
