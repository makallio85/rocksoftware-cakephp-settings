<?php
/**
 * One section's form: its fields, Save and "Last changed … by …". Bootstrap 5
 * markup, no script.
 *
 * @var \Cake\View\View $this
 * @var array{section: \RockSoftware\Settings\SettingSection, fields: list<array{declaration: \RockSoftware\Settings\SettingDeclaration, value: string|bool, error: string|null}>, lastChange: \RockSoftware\Settings\Model\Entity\SettingValue|null} $form
 * @var bool $isSingleSection
 * @var string $displayTimezone
 */

use RockSoftware\Settings\Controller\SettingsController;
use RockSoftware\Settings\SettingType;

$section = $form['section'];
$owner = $section->owner;
?>
<section
    class="card border-0 shadow-sm mb-3"
    id="settings-<?= h($owner) ?>"
    aria-labelledby="settings-<?= h($owner) ?>-title"
>
    <div class="card-body p-3 p-md-4">
        <?php if (!$isSingleSection) : ?>
            <h2 class="h6 fw-semibold mb-1" id="settings-<?= h($owner) ?>-title"><?= h($section->title()) ?></h2>
            <?php if ($section->description() !== null) : ?>
                <p class="small text-body-secondary mb-3"><?= h($section->description()) ?></p>
            <?php endif; ?>
        <?php endif; ?>
        <?= $this->Form->create(null, [
            'url' => [
                'plugin' => 'RockSoftware/Settings',
                'controller' => 'Settings',
                'action' => 'save',
                '_method' => 'POST',
                $owner,
            ],
        ]) ?>
        <?php if ($isSingleSection) : ?>
            <?= $this->Form->hidden('return_to', ['value' => SettingsController::RETURN_TO_SECTION]) ?>
        <?php endif; ?>
        <?php foreach ($form['fields'] as $field) : ?>
            <?php
            $declaration = $field['declaration'];
            $name = 'values.' . $declaration->name();
            $id = 'settings-' . $owner . '-' . str_replace('_', '-', $declaration->name());
            $help = $declaration->help();
            $error = $field['error'];
            $describedBy = trim(($help !== null ? $id . '-help ' : '') . ($error !== null ? $id . '-error' : ''));
            $common = [
                'id' => $id,
                'value' => $field['value'],
                'required' => $declaration->isRequired,
            ];
            if ($describedBy !== '') {
                $common['aria-describedby'] = $describedBy;
            }
            if ($error !== null) {
                $common['aria-invalid'] = 'true';
            }
            $invalid = $error !== null ? ' is-invalid' : '';
            $isOptional = !$declaration->isRequired && $declaration->type !== SettingType::YesNo;
            ?>
            <div class="mb-3">
                <?php if ($declaration->type === SettingType::YesNo) : ?>
                    <div class="form-check form-switch">
                        <?= $this->Form->checkbox($name, [
                            'id' => $id,
                            'value' => '1',
                            'hiddenField' => '0',
                            'checked' => $field['value'] === true,
                            'class' => 'form-check-input' . $invalid,
                            'role' => 'switch',
                        ] + array_intersect_key($common, ['aria-describedby' => true, 'aria-invalid' => true])) ?>
                        <label class="form-check-label fw-semibold" for="<?= h($id) ?>">
                            <?= h($declaration->label()) ?>
                        </label>
                    </div>
                <?php else : ?>
                    <label class="form-label fw-semibold" for="<?= h($id) ?>">
                        <?= h($declaration->label()) ?>
                        <?php if ($isOptional) : ?>
                            <span class="fw-normal text-body-secondary">
                                <?= h(__d('rock_software_settings', '(optional)')) ?>
                            </span>
                        <?php endif; ?>
                    </label>
                    <?php if ($declaration->type === SettingType::Text) : ?>
                        <?= $this->Form->text($name, $common + [
                            'class' => 'form-control' . $invalid,
                            'maxlength' => $declaration->maxLength,
                        ]) ?>
                    <?php elseif ($declaration->type === SettingType::LongText) : ?>
                        <?= $this->Form->textarea($name, $common + [
                            'class' => 'form-control' . $invalid,
                            'rows' => 4,
                            'maxlength' => $declaration->maxLength,
                        ]) ?>
                    <?php elseif ($declaration->type === SettingType::EmailAddresses) : ?>
                        <?= $this->Form->textarea($name, $common + [
                            'class' => 'form-control' . $invalid,
                            'rows' => 3,
                            'autocomplete' => 'off',
                            'spellcheck' => 'false',
                        ]) ?>
                    <?php elseif ($declaration->type === SettingType::WholeNumber) : ?>
                        <?= $this->Form->text($name, $common + [
                            'type' => 'number',
                            'step' => 1,
                            'min' => $declaration->min,
                            'max' => $declaration->max,
                            'inputmode' => 'numeric',
                            'class' => 'form-control' . $invalid,
                        ]) ?>
                    <?php else : ?>
                        <?= $this->Form->select($name, $declaration->choices(), $common + [
                            'empty' => $declaration->isRequired ? false : __d('rock_software_settings', '(none)'),
                            'class' => 'form-select' . $invalid,
                        ]) ?>
                    <?php endif; ?>
                <?php endif; ?>
                <?php if ($error !== null) : ?>
                    <div class="invalid-feedback d-block" id="<?= h($id) ?>-error"><?= h($error) ?></div>
                <?php endif; ?>
                <?php if ($help !== null) : ?>
                    <div class="form-text" id="<?= h($id) ?>-help"><?= h($help) ?></div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <?= $this->Form->button(__d('rock_software_settings', 'Save'), ['class' => 'btn btn-primary']) ?>
            <?php $lastChange = $form['lastChange']; ?>
            <?php if ($lastChange !== null) : ?>
                <?php
                $changedAt = $lastChange->modified->setTimezone($displayTimezone)->i18nFormat('d.M.yyyy HH.mm');
                $changedBy = $lastChange->changed_by_name;
                ?>
                <span class="small text-body-secondary">
                    <?= h($changedBy === null
                        ? __d('rock_software_settings', 'Last changed {0}', $changedAt)
                        : __d('rock_software_settings', 'Last changed {0} by {1}', $changedAt, $changedBy)) ?>
                </span>
            <?php endif; ?>
        </div>
        <?= $this->Form->end() ?>
    </div>
</section>
