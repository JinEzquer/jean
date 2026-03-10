<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isEdit = $options['is_edit'] ?? false;
        $isCurrentUser = $options['is_current_user'] ?? false;
        
        $builder
            ->add('email', EmailType::class, [
                'label' => 'Email Address',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'user@example.com'
                ]
            ])
            ->add('firstName', TextType::class, [
                'label' => 'First Name',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'John'
                ]
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Last Name',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Doe'
                ]
            ]);

        // Only show role selection for non-current users and if not editing own account
        if (!$isCurrentUser) {
            $builder->add('roles', ChoiceType::class, [
                'label' => 'Role',
                'choices' => [
                    'User' => 'ROLE_USER',
                    'Staff' => 'ROLE_STAFF',
                    'Admin' => 'ROLE_ADMIN',
                ],
                'multiple' => true,
                'expanded' => false,
                'attr' => [
                    'class' => 'form-select',
                ],
                'required' => true,
            ]);
        }

        // Only show active status for non-current users
        if (!$isCurrentUser) {
            $builder->add('isActive', CheckboxType::class, [
                'label' => 'Active',
                'required' => false,
                'label_attr' => ['class' => 'form-check-label'],
                'attr' => ['class' => 'form-check-input'],
            ]);
        }

        // Password field (not required for edit unless provided)
        $passwordOptions = [
            'mapped' => false,
            'required' => !$isEdit, // Required only for new users
            'attr' => [
                'autocomplete' => 'new-password',
                'class' => 'form-control',
                'placeholder' => $isEdit ? 'Leave blank to keep current password' : ''
            ],
            'constraints' => $isEdit ? [] : [
                new NotBlank([
                    'message' => 'Please enter a password',
                ]),
                new Length([
                    'min' => 6,
                    'minMessage' => 'Your password should be at least {{ limit }} characters',
                    'max' => 4096, // max length allowed by Symfony for security reasons
                ]),
            ],
        ];

        if ($isEdit) {
            $passwordOptions['help'] = 'Leave blank to keep current password';
        }

        $builder->add('plainPassword', PasswordType::class, $passwordOptions);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'is_edit' => false,
            'is_current_user' => false,
        ]);
    }
}
