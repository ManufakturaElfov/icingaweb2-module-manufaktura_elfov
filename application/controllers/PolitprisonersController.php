<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
namespace Icinga\Module\Manufaktura_elfov\Controllers;

use DateTime;
use Icinga\Exception\NotFoundError;
use Icinga\Module\Manufaktura_elfov\CommonController;
use Icinga\Module\Manufaktura_elfov\Db;
use Icinga\Module\Manufaktura_elfov\ExcelLists;
use Icinga\Module\Manufaktura_elfov\Forms\AwarenessForm;
use Icinga\Module\Manufaktura_elfov\InfoLinks;
use Icinga\Web\Controller;
use Icinga\Web\Url;
use Icinga\Web\Widget\Tabs;
use PDO;

class PolitprisonersController extends Controller
{
    use CommonController;

    public function allAction(): void
    {
        $this->view->excelLists = ExcelLists::create()->select(['uuid', 'display_name'])->fetchPairs();

        $this->view->rows = $query = Db::getPdo()->prepare(
            'SELECT id, name, born, source, awareness,'
            . ' last_seen<>(SELECT last_import FROM polit_prisoner_source WHERE id=pp.source) vanished'
            . ' FROM polit_prisoner pp ORDER BY name'
        );

        $query->execute();
        $query->setFetchMode(PDO::FETCH_OBJ);
        $this->setupTabs($this->view, $this->addTabByBirthday($this->addTabAll(new Tabs)), 'all');
    }

    public function bybirthdayAction(): void
    {
        $query = Db::getPdo()->prepare(
            'SELECT born_month, COUNT(*) amount FROM polit_prisoner GROUP BY born_month ORDER BY born_month'
        );

        $query->execute();

        $this->view->rows = $rows = $query->fetchAll(PDO::FETCH_OBJ);

        foreach ($rows as $row) {
            $row->month_name = $this->getMonthName($row->born_month);
        }

        $this->setupTabs($this->view, $this->addTabByBirthday($this->addTabAll(new Tabs)), 'bybirthday');
    }

    public function bybirthmonthAction(): void
    {
        $month = $this->params->getRequired('month');

        if (preg_match('~\D~', $month)) {
            throw new NotFoundError('');
        }

        $month = (int)$month;

        if ($month < 0 || $month > 12) {
            throw new NotFoundError('');
        }

        $this->view->excelLists = ExcelLists::create()->select(['uuid', 'display_name'])->fetchPairs();

        $this->view->rows = $query = Db::getPdo()->prepare(
            'SELECT id, born, name, source, awareness,'
            . ' last_seen<>(SELECT last_import FROM polit_prisoner_source WHERE id=pp.source) vanished'
            . ' FROM polit_prisoner pp WHERE born_month=:born_month ORDER BY born_dom, name'
        );

        $query->execute(['born_month' => $month]);
        $query->setFetchMode(PDO::FETCH_OBJ);
        $this->setupTabs($this->view, $this->addTabByMonth($this->addTabByBirthday(new Tabs), $month), 'bymonth');
    }

    public function viewAction(): void
    {
        $id = $this->params->getRequired('id');

        if (preg_match('~\D~', $id)) {
            throw new NotFoundError('');
        }

        $id = (int)$id;

        $query = Db::getPdo()->prepare(
            'SELECT name, born, source, awareness,'
            . ' last_seen<>(SELECT last_import FROM polit_prisoner_source WHERE id=pp.source) vanished'
            . ' FROM polit_prisoner pp WHERE id=:id'
        );

        $query->execute(['id' => $id]);

        $this->view->politPrisoner = $politPrisoner = $query->fetchObject();
        $query = null;

        if ($politPrisoner === false) {
            throw new NotFoundError('');
        }

        $politPrisoner->id = $id;

        $this->view->excelLists = ExcelLists::create()->select(['uuid', 'display_name'])
            ->where('uuid', $politPrisoner->source)->fetchPairs();

        $this->view->infoLinks = $infoLinks = InfoLinks::create()->select(['display_name', 'url'])->fetchAll();

        foreach ($infoLinks as $infoLink) {
            $infoLink->url = preg_replace_callback(
                '~\$([^$]*)\$~',
                function (array $matches) use ($politPrisoner) {
                    switch ($matches[1]) {
                        case '':
                            return '$';
                        case 'name':
                            return rawurlencode($politPrisoner->name);
                        default:
                            return $matches[0];
                    }
                },
                $infoLink->url
            );
        }

        $this->view->fields = $query = Db::getPdo()->prepare(
            'SELECT (SELECT name FROM polit_prisoner_field WHERE id=ppa.field) AS name, value,'
            . ' last_seen<>(SELECT last_import FROM polit_prisoner_source WHERE id=:source) vanished'
            . ' FROM polit_prisoner_attr ppa WHERE polit_prisoner=:id'
            . ' ORDER BY (SELECT name FROM polit_prisoner_field WHERE id=ppa.field)'
        );

        $query->execute(['id' => $id, 'source' => $politPrisoner->source]);
        $query->setFetchMode(PDO::FETCH_OBJ);

        $tabs = new Tabs;

        switch ($this->getParam('from')) {
            case 'all':
                $this->addTabAll($tabs);
                break;
            case 'month':
                $this->addTabByMonth(
                    $tabs, $politPrisoner->born === null ? 0 : (int)(new DateTime($politPrisoner->born))->format('n')
                );
        }

        $tabs->add('polit_prisoner', [
            'title' => preg_replace('~( \S)\S+~u', '\\1.', $politPrisoner->name),
            'icon' => 'user',
            'url' => Url::fromRequest()
        ]);

        $this->setupTabs($this->view, $tabs, 'polit_prisoner');
    }

    public function awarenessAction(): void
    {
        $politPrisoner = $this->params->getRequired('politprisoner');

        if (preg_match('~\D~', $politPrisoner)) {
            throw new NotFoundError('');
        }

        $politPrisoner = (int)$politPrisoner;
        $query = Db::getPdo()->prepare('SELECT awareness FROM polit_prisoner WHERE id=:id');

        $query->execute(['id' => $politPrisoner]);

        $awareness = $query->fetch(PDO::FETCH_COLUMN);
        $query = null;

        if ($awareness === false) {
            throw new NotFoundError('');
        }

        $this->view->form = $form = (new AwarenessForm)
            ->setPolitPrisoner($politPrisoner)
            ->setAwarenessScore($awareness)
            ->setRedirectUrl(Url::fromPath('manufaktura_elfov/politprisoners/view', ['id' => $politPrisoner]));

        $form->handleRequest();

        $this->view->rows = $query = Db::getPdo()->prepare(
            'SELECT ppa.edited, wu.name, ppa.awareness, ppa.comment'
            . ' FROM polit_prisoner_awareness ppa INNER JOIN web_user wu ON wu.id=ppa.editor'
            . ' WHERE ppa.polit_prisoner=:polit_prisoner ORDER BY ppa.edited DESC'
        );

        $query->execute(['polit_prisoner' => $politPrisoner]);
        $query->setFetchMode(PDO::FETCH_OBJ);

        $this->view->title = $this->translate('Awareness score');
    }

    private function getMonthName(int $month): string
    {
        switch ($month) {
            case 1:
                return $this->translate('January');
            case 2:
                return $this->translate('February');
            case 3:
                return $this->translate('March');
            case 4:
                return $this->translate('April');
            case 5:
                return $this->translate('May');
            case 6:
                return $this->translate('June');
            case 7:
                return $this->translate('July');
            case 8:
                return $this->translate('August');
            case 9:
                return $this->translate('September');
            case 10:
                return $this->translate('October');
            case 11:
                return $this->translate('November');
            case 12:
                return $this->translate('December');
            default:
                return $this->translate('Unknown');
        }
    }

    private function addTabAll(Tabs $tabs): Tabs
    {
        return $tabs->add('all', [
            'title' => $this->translate('All'),
            'icon' => 'th-list',
            'url' => 'manufaktura_elfov/politprisoners/all'
        ]);
    }

    private function addTabByBirthday(Tabs $tabs): Tabs
    {
        return $tabs->add('bybirthday', [
            'title' => $this->translate('By birthday'),
            'icon' => 'calendar',
            'url' => 'manufaktura_elfov/politprisoners/bybirthday'
        ]);
    }

    private function addTabByMonth(Tabs $tabs, int $month): Tabs
    {
        return $tabs->add('bymonth', [
            'title' => $this->getMonthName($month),
            'icon' => 'calendar',
            'url' => Url::fromPath('manufaktura_elfov/politprisoners/bybirthmonth', ['month' => $month])
        ]);
    }
}
