<?php

namespace App\Controller\Admin;

use App\Entity\Review;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;

class ReviewCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Review::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions->disable(Action::NEW);
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            AssociationField::new('user_id')
                ->setDisabled(),
            AssociationField::new('album_id')
                ->setDisabled(),
            IntegerField::new('rating'),
            TextareaField::new('comment'),
            BooleanField::new('is_favorite'),
            DateTimeField::new('listened_at')
                ->hideOnForm(),
            DateTimeField::new('created_at')
                ->hideOnForm(),
            DateTimeField::new('updated_at')
                ->hideOnForm(),
            DateTimeField::new('deleted_at')
                ->hideOnForm(),
            TextareaField::new('deleted_by')
                ->hideOnForm(),
        ];
    }

    public function updateEntity(EntityManagerInterface $entityManager, object $entityInstance): void
    {
        if (!$entityInstance instanceof Review) {
            throw new \InvalidArgumentException('Expected a Review entity.');
        }

        $entityInstance->setUpdatedAt(new \DateTimeImmutable());
        parent::updateEntity($entityManager, $entityInstance);
    }
}
