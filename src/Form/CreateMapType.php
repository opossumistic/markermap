<?php

namespace App\Form;

use App\Form\Data\CreateMapData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<CreateMapData>
 */
final class CreateMapType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Name der Map',
                'attr' => [
                    'placeholder' => 'z. B. Tischtennis Hamburg',
                    'data-map-slug-target' => 'name',
                    'data-action' => 'input->map-slug#nameInput',
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Deine E-Mail',
                'help' => 'Bestätigungslink + später Login zum Moderieren.',
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Kurzbeschreibung (optional)',
                'required' => false,
                'attr' => ['rows' => 2],
            ])
            ->add('centerLat', NumberType::class, [
                'label' => false,
                'html5' => true,
                'scale' => 6,
                'attr' => [
                    'step' => 'any',
                    'data-map-viewport-target' => 'centerLat',
                    'class' => 'visually-hidden',
                    'tabindex' => '-1',
                    'aria-hidden' => 'true',
                ],
            ])
            ->add('centerLng', NumberType::class, [
                'label' => false,
                'html5' => true,
                'scale' => 6,
                'attr' => [
                    'step' => 'any',
                    'data-map-viewport-target' => 'centerLng',
                    'class' => 'visually-hidden',
                    'tabindex' => '-1',
                    'aria-hidden' => 'true',
                ],
            ])
            ->add('defaultZoom', NumberType::class, [
                'label' => false,
                'html5' => true,
                'scale' => 1,
                'attr' => [
                    'step' => '0.5',
                    'min' => 1,
                    'max' => 18,
                    'data-map-viewport-target' => 'zoom',
                    'class' => 'visually-hidden',
                    'tabindex' => '-1',
                    'aria-hidden' => 'true',
                ],
            ])
            ->add('website', TextType::class, [
                'label' => false,
                'required' => false,
                'mapped' => true,
                'attr' => [
                    'tabindex' => '-1',
                    'autocomplete' => 'off',
                    'aria-hidden' => 'true',
                    'class' => 'hp-field',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CreateMapData::class,
        ]);
    }
}
