<?php
/**
 * Settings: every section, each its own form and Save. Bootstrap 5 markup and
 * no script; an application can restyle it by overriding
 * `templates/plugin/RockSoftware/Settings/Settings/index.php`.
 *
 * @var \Cake\View\View $this
 * @var list<array<string, mixed>> $forms
 * @var bool $isSingleSection
 * @var string $pageTitle
 */
$this->assign('title', $pageTitle);
?>
<div class="rock-software-settings">
    <?= $this->element('RockSoftware/Settings.settings_breadcrumb') ?>
    <div class="mb-3">
        <h1 class="h4 mb-1"><?= h($pageTitle) ?></h1>
    </div>
    <div class="row">
        <div class="col-12 col-xl-9">
            <?php foreach ($forms as $form) : ?>
                <?= $this->element('RockSoftware/Settings.settings_section_form', ['form' => $form]) ?>
            <?php endforeach; ?>
            <?php if ($forms === []) : ?>
                <p class="text-body-secondary"><?= h(__d('rock_software_settings', 'No settings yet.')) ?></p>
            <?php endif; ?>
        </div>
    </div>
</div>
