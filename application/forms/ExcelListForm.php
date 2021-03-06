<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
namespace Icinga\Module\Manufaktura_elfov\Forms;

class ExcelListForm extends RepoForm
{
    protected function createInsertElements(array $formData): void
    {
        parent::createInsertElements($formData);

        $this->addElement('text', 'url', [
            'label' => $this->translate('URL'),
            'required' => true,
            'validators' => [[
                'validator' => 'regex',
                'options' => ['pattern' => '~^https?://~']
            ]]
        ]);

        $this->addElement('text', 'name_column', [
            'label' => $this->translate('Name column'),
            'required' => true
        ]);

        $this->addElement('text', 'born_column', [
            'label' => $this->translate('Born column')
        ]);
    }
}
