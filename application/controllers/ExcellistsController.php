<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
namespace Icinga\Module\Manufaktura_elfov\Controllers;

use Icinga\Module\Manufaktura_elfov\CrudController;
use Icinga\Module\Manufaktura_elfov\ExcelLists;
use Icinga\Module\Manufaktura_elfov\Forms\ExcelListForm;
use Icinga\Module\Manufaktura_elfov\Forms\RepoForm;
use Icinga\Module\Manufaktura_elfov\IniRepo;

class ExcellistsController extends CrudController
{
    protected function getTab(): string
    {
        return 'excellists';
    }

    protected function getRepo(): IniRepo
    {
        return ExcelLists::create();
    }

    protected function newForm(): RepoForm
    {
        return (new ExcelListForm)->setRepository(ExcelLists::create())->setRedirectUrl('manufaktura_elfov/excellists');
    }
}
