<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * The plugin's own tables. Nothing of the application's has to exist first.
 *
 * - `rock_software_settings_values`: one row per stored setting: its key
 *   (`<owner>.<name>`), its value as JSON, and who changed it last and when
 *   (`modified`). A setting without a row has its declared default.
 * - `rock_software_settings_changes`: one row per changed setting per save:
 *   the key, the value before (SQL `NULL` when nothing was stored, so the
 *   default applied) and after, as JSON, who and when.
 *
 * Who is kept as the identity's identifier and a display name, so the history
 * reads the same after the person is gone and the plugin needs no users table.
 */
class CreateRockSoftwareSettingsTables extends BaseMigration
{
    public bool $autoId = false;

    /**
     * @return void
     */
    public function up(): void
    {
        $this->table('rock_software_settings_values', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'integer', ['autoIncrement' => true, 'signed' => false, 'null' => false])
            ->addColumn('setting_key', 'string', ['limit' => 191, 'null' => false])
            ->addColumn('value', 'text', ['null' => false])
            ->addColumn('changed_by_identifier', 'string', ['limit' => 191, 'null' => true, 'default' => null])
            ->addColumn('changed_by_name', 'string', ['limit' => 255, 'null' => true, 'default' => null])
            ->addColumn('created', 'datetime', ['null' => false])
            ->addColumn('modified', 'datetime', ['null' => false])
            ->addIndex(['setting_key'], ['unique' => true, 'name' => 'rss_values_setting_key'])
            ->create();

        $this->table('rock_software_settings_changes', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'integer', ['autoIncrement' => true, 'signed' => false, 'null' => false])
            ->addColumn('setting_key', 'string', ['limit' => 191, 'null' => false])
            ->addColumn('old_value', 'text', ['null' => true, 'default' => null])
            ->addColumn('new_value', 'text', ['null' => false])
            ->addColumn('changed_by_identifier', 'string', ['limit' => 191, 'null' => true, 'default' => null])
            ->addColumn('changed_by_name', 'string', ['limit' => 255, 'null' => true, 'default' => null])
            ->addColumn('created', 'datetime', ['null' => false])
            ->addIndex(['setting_key', 'created'], ['name' => 'rss_changes_setting_key_created'])
            ->create();
    }

    /**
     * @return void
     */
    public function down(): void
    {
        $this->table('rock_software_settings_changes')->drop()->save();
        $this->table('rock_software_settings_values')->drop()->save();
    }
}
