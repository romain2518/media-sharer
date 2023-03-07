<?php

namespace App\Form;

use App\Entity\User;
use Rollerworks\Component\PasswordStrength\Validator\Constraints\PasswordStrength;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotCompromisedPassword;

class EditLoginsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', options: [
                'empty_data' => '',
            ])
            ->add('currentPassword', PasswordType::class, [
                'constraints' => [
                    new NotBlank([
                        'message' => 'Votre mot de passe actuel est requis pour modifier vos identifiants.',
                    ]),
                ],
                'mapped' => false,
            ])
            ->add('newPassword', RepeatedType::class, [
                // Instead of being set onto the object directly,
                // this is read and encoded in the controller
                'type' => PasswordType::class,
                'options' => [
                    'attr' => [
                        'autocomplete' => 'new-password',
                        'autocomplete' => 'new-password',
                    ],
                ],
                'invalid_message' => 'Les mots de passes sont différents.',
                'required' => false,
                'mapped' => false,
                'first_options' => [
                    'constraints' => [
                        new Length([
                            // max length allowed by Symfony for security reasons
                            'max' => 4096,
                        ]),
                        new NotCompromisedPassword,
                        new PasswordStrength([
                            'minStrength' => 4,
                            'minLength' => 8,
                            // @see https://github.com/rollerworks/PasswordStrengthValidator/blob/main/docs/strength-validation.md
                        ])
                    ],
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
