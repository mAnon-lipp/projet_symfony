<?php

namespace App\Controller\Admin;

use App\Entity\Artist;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class ArtistCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Artist::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            TextField::new('name'),
            ImageField::new('image_url')
                ->setUploadDir('public/uploads/artists')
                ->setBasePath('uploads/artists')
                ->setUploadedFileNamePattern('[slug]-[uuid].[extension]'),
            DateTimeField::new('created_at')
                ->hideOnForm(),
        ];
    }

    public function createEntity(string $entityFqcn): object
    {
        $artist = new Artist();
        $artist->setCreatedAt(new \DateTimeImmutable());

        return $artist;
    }
}
