<?php
declare(strict_types=1);

namespace RockSoftware\Settings\Test\TestCase\Controller;

use Cake\Core\Configure;
use Cake\Event\EventInterface;
use Cake\Event\EventManager;
use Cake\I18n\I18n;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use RockSoftware\Settings\SettingDeclaration;
use RockSoftware\Settings\Settings;
use RockSoftware\Settings\SettingsPlugin;
use RockSoftware\Settings\TestSuite\Fixture\SettingsFixtures;

class SettingsControllerTest extends TestCase
{
    use IntegrationTestTrait;

    protected array $fixtures = SettingsFixtures::ALL;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enableCsrfToken();
        $this->enableRetainFlashMessages();
        Settings::resetDeclarations();
        Settings::clearCache();
        Configure::write(
            'RockSoftwareSettings.access',
            fn($identity, $request): bool => $identity['email'] === 'admin@example.test',
        );
    }

    protected function tearDown(): void
    {
        Configure::write('RockSoftwareSettings', SettingsPlugin::DEFAULTS);
        Settings::resetDeclarations();
        Settings::clearCache();
        I18n::setLocale('en_US');
        parent::tearDown();
    }

    private function signIn(): void
    {
        $this->session(['Auth' => ['id' => 1, 'name' => 'Alex Example', 'email' => 'admin@example.test']]);
    }

    public function testWithoutAnAccessCallbackNobodyMayOpenIt(): void
    {
        Configure::write('RockSoftwareSettings.access', null);
        $this->signIn();

        $this->get('/settings');
        $this->assertResponseCode(403);

        $this->post('/settings/application', ['values' => ['name' => 'Example App']]);
        $this->assertResponseCode(403);
        $this->assertFalse(Settings::isStored('application.name'));
    }

    public function testNobodyWhoIsNotSignedInMayOpenIt(): void
    {
        Configure::write('RockSoftwareSettings.access', fn(): bool => true);

        $this->get('/settings');

        $this->assertResponseCode(403);
    }

    public function testAccessIsTheApplicationsDecision(): void
    {
        $this->session(['Auth' => ['id' => 2, 'email' => 'someone@example.test']]);

        $this->get('/settings/application');
        $this->assertResponseCode(403);

        $this->post('/settings/application', ['values' => ['name' => 'Example App']]);
        $this->assertResponseCode(403);
        $this->assertFalse(Settings::isStored('application.name'));
    }

    public function testASignedOutVisitorIsSentToSignInByTheApplication(): void
    {
        // What the application's Authentication component does at Controller.startup.
        $signIn = function (EventInterface $event): void {
            /** @var \Cake\Controller\Controller $controller */
            $controller = $event->getSubject();
            if ($controller->getRequest()->getAttribute('identity') === null) {
                $event->setResult($controller->redirect('/sign-in?redirect=/settings'));
                $event->stopPropagation();
            }
        };
        EventManager::instance()->on('Controller.startup', $signIn);

        try {
            $this->get('/settings');
            $this->assertRedirect('/sign-in?redirect=/settings');
        } finally {
            EventManager::instance()->off('Controller.startup', $signIn);
        }
    }

    public function testThePageShowsEverySectionWithItsFields(): void
    {
        Settings::declareSection('reports', 'Reports', 'How reports look.');
        Settings::declare(SettingDeclaration::yesNo('reports.is_footer_shown', 'Show the footer', default: true));
        Settings::declare(SettingDeclaration::choice('reports.paper', 'Paper', ['a4' => 'A4', 'letter' => 'Letter']));
        Settings::declare(SettingDeclaration::wholeNumber('reports.rows_per_page', 'Rows per page', default: 50));
        Settings::declare(SettingDeclaration::longText('reports.footer', 'Footer'));
        $this->signIn();

        $this->get('/settings');

        $this->assertResponseOk();
        $this->assertResponseContains('<h1 class="h4 mb-1">Settings</h1>');
        $this->assertResponseContains('id="settings-application"');
        $this->assertResponseContains('id="settings-reports"');
        $this->assertResponseContains('Application name');
        $this->assertResponseContains('Line under the name');
        $this->assertResponseContains('(optional)');
        $this->assertResponseContains('value="Application"');
        $this->assertResponseContains('action="/settings/application"');
        $this->assertResponseContains('action="/settings/reports"');
        $this->assertResponseContains('type="checkbox" name="values[is_footer_shown]" value="1"');
        $this->assertResponseContains('<select name="values[paper]"');
        $this->assertResponseContains('type="number"');
        $this->assertResponseContains('<textarea name="values[footer]"');
        $this->assertResponseNotContains('Last changed');
        $this->assertResponseNotContains('<script');
        $this->assertResponseNotContains(' onclick=');
    }

    public function testOneSectionHasAPageOfItsOwn(): void
    {
        $this->signIn();

        $this->get('/settings/application');

        $this->assertResponseOk();
        $this->assertResponseContains('id="settings-application-title">Application</h1>');
        $this->assertResponseContains('How this application names itself');
        $this->assertResponseContains('name="return_to" value="section"');
        $this->assertResponseContains('<a href="/settings">Settings</a>');
    }

    public function testAnOwnerWithoutSettingsHasNoPage(): void
    {
        $this->signIn();

        $this->get('/settings/nobody');

        $this->assertResponseCode(404);
    }

    public function testSavingStoresTheValuesAndShowsWhoChangedThem(): void
    {
        $this->signIn();

        $this->post('/settings/application', [
            'values' => ['name' => 'Example App', 'organization_name' => 'Example Ltd'],
            'return_to' => 'section',
        ]);

        $this->assertRedirect('/settings/application');
        $this->assertFlashMessage('Saved.');
        $this->assertSame('Example App', Settings::get('application.name'));
        $this->assertSame('Example Ltd', Settings::get('application.organization_name'));
        $change = Settings::changes('application.name')[0];
        $this->assertSame('1', $change->changed_by_identifier);
        $this->assertSame('Alex Example', $change->changed_by_name);

        $this->get('/settings/application');
        $this->assertResponseContains('value="Example App"');
        $this->assertResponseRegExp('/Last changed \d{1,2}\.\d{1,2}\.\d{4} \d{2}\.\d{2} by Alex Example/');
    }

    public function testSavingFromThePageOfEverySectionReturnsThere(): void
    {
        $this->signIn();

        $this->post('/settings/application', ['values' => ['name' => 'Example App']]);

        $this->assertRedirect('/settings#settings-application');
    }

    public function testASaveWithoutChangesSaysSo(): void
    {
        Settings::set('application.name', 'Example App');
        $this->signIn();

        $this->post('/settings/application', ['values' => ['name' => 'Example App']]);

        $this->assertFlashMessage('Nothing changed.');
        $this->assertCount(1, Settings::changes('application.name'));
    }

    public function testARefusedSaveShowsTheReasonAndKeepsWhatWasTyped(): void
    {
        $this->signIn();

        $this->post('/settings/application', [
            'values' => ['name' => '', 'organization_name' => 'Example Ltd'],
            'return_to' => 'section',
        ]);

        $this->assertResponseOk();
        $this->assertFlashMessage('Not saved. Check the fields marked in red.');
        $this->assertResponseContains('is-invalid');
        $this->assertResponseContains('aria-invalid="true"');
        $this->assertResponseContains('id="settings-application-name-error">This is required.</div>');
        $this->assertResponseContains('value="Example Ltd"');
        $this->assertFalse(Settings::isStored('application.organization_name'));
    }

    public function testEmailAddressesAreAskedOnePerLine(): void
    {
        Settings::declare(SettingDeclaration::emailAddresses('reports.recipients', 'Recipients'));
        Settings::set('reports.recipients', ['alex@example.test', 'sam@example.test']);
        $this->signIn();

        $this->get('/settings/reports');
        $this->assertResponseOk();
        $this->assertResponseContains("alex@example.test\nsam@example.test</textarea>");

        $this->post('/settings/reports', [
            'values' => ['recipients' => "alex@example.test\nwrong"],
            'return_to' => 'section',
        ]);
        $this->assertResponseOk();
        $this->assertResponseContains('id="settings-reports-recipients-error">Not an email address: wrong</div>');
        $this->assertResponseContains("alex@example.test\nwrong</textarea>", 'What was typed is kept.');
    }

    public function testSavingNeedsAPost(): void
    {
        $this->signIn();

        $this->put('/settings/application', ['values' => ['name' => 'Example App']]);

        $this->assertResponseCode(404);
        $this->assertFalse(Settings::isStored('application.name'));
    }

    public function testThePageSpeaksFinnish(): void
    {
        I18n::setLocale('fi_FI');
        $this->signIn();

        $this->get('/settings/application');

        $this->assertResponseOk();
        $this->assertResponseContains('Sovelluksen nimi');
        $this->assertResponseContains('Rivi nimen alla');
        $this->assertResponseContains('Tallenna');
    }

    public function testThePathIsTheApplicationsChoice(): void
    {
        Configure::write('RockSoftwareSettings.path', '/admin/settings');
        $this->signIn();

        $this->get('/admin/settings/application');

        $this->assertResponseOk();
        $this->assertResponseContains('action="/admin/settings/application"');
    }
}
