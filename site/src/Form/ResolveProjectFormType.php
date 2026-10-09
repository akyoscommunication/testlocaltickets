<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

final class ResolveProjectFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('project_key', ChoiceType::class, [
            'label' => 'Projet',
            'choices' => array_combine($options['project_keys'], $options['project_keys']),
            'placeholder' => 'Choisir un projet…',
            'constraints' => [new NotBlank()],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'project_keys' => [],
        ]);
        $resolver->setAllowedTypes('project_keys', 'array');
    }
}
