<?php
/**
 * The page's own breadcrumb, for an application whose pages write theirs in
 * the template. An application whose layout builds the breadcrumb sets the
 * `breadcrumb` view variable, and then none is shown here.
 *
 * @var \Cake\View\View $this
 * @var array{title: string, url?: array<string, mixed>|string|null}|string|null $breadcrumbParent
 * @var bool $isSingleSection
 * @var string $pageTitle
 */
$parent = is_string($breadcrumbParent) ? ['title' => $breadcrumbParent] : $breadcrumbParent;
$crumbs = [];
if (is_array($parent) && isset($parent['title'])) {
    $crumbs[] = ['title' => (string)$parent['title'], 'url' => $parent['url'] ?? null];
}
if ($isSingleSection) {
    $crumbs[] = [
        'title' => __d('rock_software_settings', 'Settings'),
        'url' => ['plugin' => 'RockSoftware/Settings', 'controller' => 'Settings', 'action' => 'index'],
    ];
}
if ($crumbs === [] || $this->get('breadcrumb') !== null) {
    return;
}
?>
<nav aria-label="<?= h(__d('rock_software_settings', 'Breadcrumb')) ?>">
    <ol class="breadcrumb small mb-2">
        <?php foreach ($crumbs as $crumb) : ?>
            <li class="breadcrumb-item">
                <?= $crumb['url'] === null ? h($crumb['title']) : $this->Html->link($crumb['title'], $crumb['url']) ?>
            </li>
        <?php endforeach; ?>
        <li class="breadcrumb-item active" aria-current="page"><?= h($pageTitle) ?></li>
    </ol>
</nav>
