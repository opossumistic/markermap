<?php

namespace App\Form;

use App\Enum\LocationCategory;
use App\Form\Data\LocationCorrectionData;
use App\Validation\LocationFieldLimits;
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
                'help' => sprintf('Max. %d Zeichen.', LocationFieldLimits::DESCRIPTION_MAX),
                'attr' => [
                    'rows' => 4,
                    'maxlength' => LocationFieldLimits::DESCRIPTION_MAX,
                ],
            ]);

        if ($options['categories_enabled']) {
            $builder->add('categories', EnumType::class, [
                'class' => LocationCategory::class,
                'label' => 'Kategorien',
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'empty_data' => [],
                'choice_label' => static fn (LocationCategory $c) => $c->label(),
            ]);
        }

        $builder
            ->add('image', FileType::class, [
                'label' => 'Neues Foto (optional)',
                'required' => false,
                'help' => 'JPEG, PNG oder WebP, max. '.LocationFieldLimits::IMAGE_MAX_SIZE_LABEL.'. Wird verkleinert und ohne Metadaten gespeichert. Ohne Upload bleibt das aktuelle Foto. Sichtbar erst nach Freigabe.',
            ])
            ->add('reason', TextareaType::class, [
                'label' => 'Hinweis für die Prüfung (optional)',
                'required' => false,
                'empty_data' => null,
                'help' => sprintf('Nur intern sichtbar, max. %d Zeichen — z. B. warum die Änderung nötig ist.', LocationFieldLimits::REASON_MAX),
                'attr' => [
                    'rows' => 2,
                    'maxlength' => LocationFieldLimits::REASON_MAX,
                    'placeholder' => 'z. B. Titel war veraltet…',
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'E-Mail (optional — Rückfragen & Info bei Freigabe)',
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
            'categories_enabled' => false,
        ]);
        $resolver->setAllowedTypes('categories_enabled', 'bool');
    }
}
