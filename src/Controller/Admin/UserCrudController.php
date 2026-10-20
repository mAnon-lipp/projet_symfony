<?php

namespace App\Controller\Admin;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            TextField::new('username'),
            TextField::new('email'),
            TextField::new('password')
                ->setFormType(PasswordType::class)
                ->onlyOnForms()
                ->setRequired(false),
            ChoiceField::new('roles')
                ->setChoices([
                    'Utilisateur' => 'ROLE_USER',
                    'Administrateur' => 'ROLE_ADMIN',
                ])
                ->allowMultipleChoices(),
            DateTimeField::new('created_at'),
            DateTimeField::new('cgu_validated_at'),
        ];
    }

    public function persistEntity(EntityManagerInterface $entityManager, object $entityInstance): void
    {
        if (!$entityInstance instanceof User) {
            throw new \InvalidArgumentException('Expected a User entity.');
        }

        $this->hashPassword($entityInstance);
        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, object $entityInstance): void
    {
        if (!$entityInstance instanceof User) {
            throw new \InvalidArgumentException('Expected a User entity.');
        }

        $originalPassword = $entityManager->getUnitOfWork()->getOriginalEntityData($entityInstance)['password'] ?? null;
        if ($entityInstance->getPassword() === null || $entityInstance->getPassword() === '') {
            $entityInstance->setPassword($originalPassword);
        } else {
            $this->hashPassword($entityInstance);
        }

        parent::updateEntity($entityManager, $entityInstance);
    }

    private function hashPassword(User $user): void
    {
        $user->setPassword($this->passwordHasher->hashPassword($user, (string) $user->getPassword()));
    }
}
