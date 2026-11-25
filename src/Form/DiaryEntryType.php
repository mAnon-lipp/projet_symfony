<?php

namespace App\Form;

use App\Entity\Review;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;

class DiaryEntryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if (!$options['is_edit']) {
            $builder
                ->add('artist', TextType::class, [
                    'mapped' => false,
                    'constraints' => [
                        new NotBlank(message: 'Enter an artist.'),
                    ],
                ])
                ->add('album', TextType::class, [
                    'mapped' => false,
                    'constraints' => [
                        new NotBlank(message: 'Enter an album.'),
                    ],
                ])
                ->add('listenedAt', DateType::class, [
                    'widget' => 'single_text',
                    'input' => 'datetime_immutable',
                    'constraints' => [
                        new NotBlank(message: 'Choose a valid listening date.'),
                    ],
                ]);
        }

        $builder
            ->add('rating', ChoiceType::class, [
                'choices' => [
                    '1 star' => 1,
                    '2 stars' => 2,
                    '3 stars' => 3,
                    '4 stars' => 4,
                    '5 stars' => 5,
                ],
                'expanded' => true,
                'constraints' => [
                    new NotBlank(message: 'Choose a rating from 1 to 5 stars.'),
                    new Range(
                        min: 1,
                        max: 5,
                        notInRangeMessage: 'Choose a rating from 1 to 5 stars.',
                    ),
                ],
            ])
            ->add('comment', TextareaType::class, [
                'required' => false,
            ])
            ->add('isFavorite', CheckboxType::class, [
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Review::class,
            'is_edit' => false,
        ]);
        $resolver->setAllowedTypes('is_edit', 'bool');
    }
}
