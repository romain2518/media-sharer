<?php

namespace App\Form;

use App\Entity\User;
use App\Entity\UserReport;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UserReportType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('comment', TextareaType::class)
            ->add('reportedUser', EntityType::class, [
                'class' => User::class,
                'placeholder' => 'Choississez un utilisateur',
                'choice_label' => 'pseudo',
                'empty_data' => 0,
                'choice_attr' => function (?User $user) use(&$options) {
                    return $user === $options['targetedUser'] ? ['selected' => ''] : [];
                },
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => UserReport::class,
            'targetedUser' => null,
        ]);
    }
}
