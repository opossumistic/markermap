<?php

namespace App\Form;

use App\Enum\LocationCategory;
use App\Form\Data\LocationCorrectionData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class LocationCorrectionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('locationId', IntegerType::class, [
                'label' => false,
                'attr' => [
                    'data-map-shell-target' => 'correctionLocationId',
                    'class' => 'coord-field',
                ],
            ])
            ->add('title', TextType::class, [
                'label' => 'Bezeichnung (optional)',
                'required' => false,
                'empty_data' => null,
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Beschreibung',
                'required' => false,
                'empty_data' => null,
                'attr' => ['rows' => 4],
            ])
            ->add('categories', EnumType::class, [
                'class' => LocationCategory::class,
                'label' => 'Kategorien',
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'empty_data' => [],
                'choice_label' => static fn (LocationCategory $c) => $c->label(),
            ])
            ->add('image', FileType::class, [
                'label' => 'Neues Foto (optional)',
                'required' => false,
                'help' => 'JPEG, PNG oder WebP, max. 2 MB. Ohne Upload bleibt das aktuelle Foto. Sichtbar erst nach Freigabe.',
            ])
            ->add('email', EmailType::class, [
                'label' => 'E-Mail (optional, für Rückfragen)',
                'required' => false,
            ])
            ->add('website', TextType::class, [
                'label' => false,
                'required' => false,
                'attr' => [
                    'tabindex' => '-1',
                    'autocomplete' => 'off',
                    'aria-hidden' => 'true',
                ],
                'row_attr' => ['class' => 'hp-field'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => LocationCorrectionData::class,
        ]);
    }
}
