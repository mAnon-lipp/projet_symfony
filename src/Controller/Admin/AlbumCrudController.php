<?php

namespace App\Controller\Admin;

use App\Entity\Album;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class AlbumCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Album::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            TextField::new('title'),
            AssociationField::new('artist')
                ->autocomplete(),
            AssociationField::new('genre')
                ->autocomplete(),
            ImageField::new('cover_url')
                ->setUploadDir('public/uploads/covers')
                ->setBasePath('uploads/covers')
                ->setUploadedFileNamePattern('[slug]-[uuid].[extension]'),
            DateTimeField::new('release_date'),
            DateTimeField::new('created_at')
                ->hideOnForm(),
        ];
    }

    public function createEntity(string $entityFqcn): object
    {
        $album = new Album();
        $album->setCreatedAt(new \DateTimeImmutable());

        return $album;
    }
}
