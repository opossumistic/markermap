<?php

namespace App\Form;

use App\Enum\LocationCategory;
use App\Form\Data\NewLocationSubmissionData;
use App\Validation\LocationFieldLimits;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class NewLocationSubmissionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $categoriesEnabled = (bool) $options['categories_enabled'];
        $titleConstraints = [
            new Assert\Length(max: 180),
        ];
        if (!$categoriesEnabled) {
            $titleConstraints[] = new Assert\NotBlank(message: 'Bitte eine Bezeichnung angeben.');
        }

        $builder
            ->add('title', TextType::class, [
                'label' => $categoriesEnabled ? 'Bezeichnung (optional)' : 'Bezeichnung',
                'required' => !$categoriesEnabled,
                'help' => $categoriesEnabled
                    ? 'Falls leer, zeigen wir Kategorien (und ggf. Ort aus der Karte).'
                    : 'Kurzname für den Pin auf der Karte.',
                'constraints' => $titleConstraints,
            ])
            ->add('street', TextType::class, [
                'label' => 'Straße und Hausnummer',
                'required' => false,
                'empty_data' => null,
                'attr' => [
                    'data-map-shell-target' => 'street',
                    'autocomplete' => 'street-address',
                ],
            ])
            ->add('postalCode', TextType::class, [
                'label' => 'PLZ / Postcode',
                'required' => false,
                'empty_data' => null,
                'attr' => [
                    'autocomplete' => 'postal-code',
                    'data-map-shell-target' => 'postalCode',
                ],
            ]);

        if ($categoriesEnabled) {
            $builder->add('categories', EnumType::class, [
                'class' => LocationCategory::class,
                'label' => 'Kategorien',
                'multiple' => true,
                'expanded' => true,
                'choice_label' => static fn (LocationCategory $c) => $c->label(),
                'constraints' => [
                    new Assert\Count(min: 1, minMessage: 'Bitte mindestens eine Kategorie wählen.'),
                ],
            ]);
        }

        $builder
            ->add('description', TextareaType::class, [
                'label' => 'Kurzbeschreibung',
                'required' => false,
                'help' => sprintf('Max. %d Zeichen.', LocationFieldLimits::DESCRIPTION_MAX),
                'attr' => [
                    'rows' => 4,
                    'maxlength' => LocationFieldLimits::DESCRIPTION_MAX,
                ],
            ])
            ->add('image', FileType::class, [
                'label' => 'Foto (optional)',
                'required' => false,
                'help' => 'JPEG, PNG oder WebP, max. '.LocationFieldLimits::IMAGE_MAX_SIZE_LABEL.'. Wird verkleinert und ohne Metadaten gespeichert.',
            ])
            ->add('lat', NumberType::class, [
                'label' => false,
                'scale' => 6,
                'html5' => true,
                'attr' => [
                    'step' => '0.000001',
                    'data-map-shell-target' => 'lat',
                ],
            ])
            ->add('lng', NumberType::class, [
                'label' => false,
                'scale' => 6,
                'html5' => true,
                'attr' => [
                    'step' => '0.000001',
                    'data-map-shell-target' => 'lng',
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'E-Mail (optional — Rückfragen & Info bei Freigabe)',
                'required' => false,
            ])
            ->add('website', TextType::class, [
                'label' => false,
                'required' => false,
                'mapped' => true,
                'attr' => [
                    'tabindex' => '-1',
                    'autocomplete' => 'off',
                    'aria-hidden' => 'true',
                ],
                'row_attr' => [
                    'class' => 'hp-field',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => NewLocationSubmissionData::class,
            'categories_enabled' => false,
        ]);
        $resolver->setAllowedTypes('categories_enabled', 'bool');
    }
}
