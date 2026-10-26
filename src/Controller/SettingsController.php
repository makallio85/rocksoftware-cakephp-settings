<?php
declare(strict_types=1);

namespace RockSoftware\Settings\Controller;

use Cake\Event\EventInterface;
use Cake\Http\Exception\ForbiddenException;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;
use RockSoftware\Settings\SettingDeclaration;
use RockSoftware\Settings\Settings;
use RockSoftware\Settings\SettingSection;
use RockSoftware\Settings\SettingsPlugin;
use RockSoftware\Settings\SettingType;

/**
 * The settings page: one section per owner, each its own form and Save.
 *
 *   GET  /settings          — every section
 *   GET  /settings/<owner>  — one section
 *   POST /settings/<owner>  — save it: `values[<name>]` per field; `return_to`
 *                             `section` to come back to the one section
 *
 * Who may open and save it is the application's decision:
 * `RockSoftwareSettings.access`, a callable receiving the identity and the
 * request, asked after the application's authentication has run (a
 * signed-out visitor is sent to sign in by it, as on any other page). Without
 * one, nobody may. Nobody who is not signed in ever may.
 *
 * `Settings::EVENT_CHANGED` is fired after a save that changed something, for
 * the application's audit log.
 *
 * @property \Cake\Controller\Component\FlashComponent $Flash
 */
class SettingsController extends SettingsBaseController
{
    /**
     * Runs after the components' `startup` (priority 10), the application's
     * Authentication component among them.
     */
    public const ACCESS_CHECK_PRIORITY = 100;

    /** `return_to` of a form shown on the one-section page. */
    public const RETURN_TO_SECTION = 'section';

    /**
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();

        if (!$this->components()->has('Flash')) {
            $this->loadComponent('Flash');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function implementedEvents(): array
    {
        return parent::implementedEvents() + [
            'Controller.startup' => ['callable' => 'checkAccess', 'priority' => self::ACCESS_CHECK_PRIORITY],
        ];
    }

    /**
     * @param \Cake\Event\EventInterface<\Cake\Controller\Controller> $event The event.
     * @return void
     * @throws \Cake\Http\Exception\ForbiddenException When nobody is signed in or `access` does not allow.
     */
    public function checkAccess(EventInterface $event): void
    {
        $identity = $this->getRequest()->getAttribute('identity');
        $access = SettingsPlugin::setting('access');
        $isAllowed = $identity !== null
            && is_callable($access)
            && $access($identity, $this->getRequest()) === true;
        if (!$isAllowed) {
            throw new ForbiddenException(__d('rock_software_settings', 'You may not change the settings.'));
        }
    }

    /**
     * @return void
     */
    public function index(): void
    {
        $this->show(Settings::sections(), false);
    }

    /**
     * @param string $owner The section's owner.
     * @return void
     */
    public function section(string $owner): void
    {
        $this->show([$this->sectionOrFail($owner)], true);
    }

    /**
     * @param string $owner The section's owner.
     * @return \Cake\Http\Response|null
     */
    public function save(string $owner): ?Response
    {
        $this->getRequest()->allowMethod('post');
        $section = $this->sectionOrFail($owner);
        $isSingleSection = $this->getRequest()->getData('return_to') === self::RETURN_TO_SECTION;
        $input = $this->getRequest()->getData('values');
        $input = is_array($input) ? $input : [];

        $result = Settings::save($owner, $input, $this->getRequest()->getAttribute('identity'));
        if ($result->isSaved()) {
            if ($result->hasChanges()) {
                $this->Flash->success(__d('rock_software_settings', 'Saved.'));
            } else {
                $this->Flash->info(__d('rock_software_settings', 'Nothing changed.'));
            }

            return $this->redirect($isSingleSection
                ? ['action' => 'section', $owner]
                : ['action' => 'index', '#' => 'settings-' . $owner]);
        }

        $this->Flash->error(__d('rock_software_settings', 'Not saved. Check the fields marked in red.'));
        $sections = $isSingleSection ? [$section] : Settings::sections();
        $this->show($sections, $isSingleSection, [$owner => $input], [$owner => $result->errors]);
        $this->render($isSingleSection ? 'section' : 'index');

        return null;
    }

    /**
     * @param string $owner An owner.
     * @return \RockSoftware\Settings\SettingSection
     * @throws \Cake\Http\Exception\NotFoundException When the owner has no settings.
     */
    private function sectionOrFail(string $owner): SettingSection
    {
        $section = Settings::section($owner);
        if ($section === null) {
            throw new NotFoundException(__d('rock_software_settings', 'No such settings.'));
        }

        return $section;
    }

    /**
     * Sets the page's view variables.
     *
     * @param list<\RockSoftware\Settings\SettingSection> $sections The sections shown.
     * @param bool $isSingleSection Whether this is one section's page.
     * @param array<string, array<string, mixed>> $submitted Per owner, the values a refused save submitted.
     * @param array<string, array<string, string>> $errors Per owner, each refused field's reason.
     * @return void
     */
    private function show(array $sections, bool $isSingleSection, array $submitted = [], array $errors = []): void
    {
        $forms = [];
        foreach ($sections as $section) {
            $fields = [];
            foreach (Settings::declarations($section->owner) as $key => $declaration) {
                $name = $declaration->name();
                $isSubmitted = array_key_exists($name, $submitted[$section->owner] ?? []);
                $fields[] = [
                    'declaration' => $declaration,
                    'value' => $isSubmitted
                        ? self::formValue($declaration, $submitted[$section->owner][$name])
                        : self::formValue($declaration, Settings::get($key)),
                    'error' => $errors[$section->owner][$name] ?? null,
                ];
            }
            $forms[] = [
                'section' => $section,
                'fields' => $fields,
                'lastChange' => Settings::lastChange($section->owner),
            ];
        }

        $title = $isSingleSection && $sections !== []
            ? $sections[0]->title()
            : __d('rock_software_settings', 'Settings');
        $this->set([
            'forms' => $forms,
            'isSingleSection' => $isSingleSection,
            'pageTitle' => $title,
            // The page's title for an application's own breadcrumb (`breadcrumbTitle`).
            'breadcrumbTitle' => $title,
            'breadcrumbParent' => SettingsPlugin::setting('breadcrumbParent'),
            'displayTimezone' => SettingsPlugin::displayTimezone(),
        ]);
    }

    /**
     * A value as its form field shows it.
     *
     * @param \RockSoftware\Settings\SettingDeclaration $declaration The setting.
     * @param mixed $value A stored or submitted value.
     * @return string|bool
     */
    private static function formValue(SettingDeclaration $declaration, mixed $value): string|bool
    {
        if ($declaration->type === SettingType::YesNo) {
            return $value === true || $value === '1' || $value === 1;
        }

        return is_scalar($value) ? (string)$value : '';
    }
}
