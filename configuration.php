<?php
// SPDX-License-Identifier: AGPL-3.0-or-later

/** @var \Icinga\Application\Modules\Module $this */

$this->provideConfigTab('config', [
    'url' => 'config',
    'label' => $this->translate('Settings')
]);

$this->provideConfigTab('excellists', [
    'url' => 'excellists',
    'label' => $this->translate('Excel lists')
]);

$section = $this->menuSection(N_('Polit. prisoners'), [
    'icon' => 'users',
    'priority' => -1
]);

$section->add(N_('By birthday'), [
    'icon' => 'calendar',
    'url' => 'manufaktura_elfov/politprisoners/bybirthday',
    'priority' => 10
]);
