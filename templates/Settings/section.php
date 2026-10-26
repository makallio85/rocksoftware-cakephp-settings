<?php
/**
 * One section of the settings page, such as Settings › Application: its
 * title, the text under it, its form and Save. Bootstrap 5 markup and no
 * script; an application can restyle it by overriding
 * `templates/plugin/RockSoftware/Settings/Settings/section.php`.
 *
 * @var \Cake\View\View $this
 * @var list<array<string, mixed>> $forms
 * @var bool $isSingleSection
 * @var string $pageTitle
 */
$this->assign('title', $pageTitle);
$section = $forms[0]['section'];
?>
<div class="rock-software-settings">
    <?= $this->element('RockSoftware/Settings.settings_breadcrumb') ?>
    <div class="mb-3">
        <h1 class="h4 mb-1" id="settings-<?= h($section->owner) ?>-title"><?= h($pageTitle) ?></h1>
        <?php if ($section->description() !== null) : ?>
            <p class="text-body-secondary mb-0"><?= h($section->description()) ?></p>
        <?php endif; ?>
    </div>
    <div class="row">
        <div class="col-12 col-xl-9">
            <?= $this->element('RockSoftware/Settings.settings_section_form', ['form' => $forms[0]]) ?>
        </div>
    </div>
</div>
