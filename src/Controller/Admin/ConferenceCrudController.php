<?php

namespace App\Controller\Admin;

use App\Entity\Conference;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class ConferenceCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Conference::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('city');
        yield TextField::new('year');
        yield BooleanField::new('isInternational');
        $slug = TextField::new('slug');
        switch ($pageName) {
            case Crud::PAGE_EDIT :
                yield $slug->setFormTypeOption('disabled', true);
                break;
            case Crud::PAGE_NEW :
                break;
            default:
                yield $slug;
                break;
        }
    }

}
