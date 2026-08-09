<?php

/**
 * @file classes/migration/upgrade/EncryptApiKey.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EncryptApiKey
 *
 * @brief Encrypt pre-existing plaintext iThenticate API keys stored in plugin_settings.
 * 
 */

namespace APP\plugins\generic\plagiarism\classes\migration\upgrade;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use PKP\db\DAORegistry;
use PKP\install\DowngradeNotSupportedException;
use PKP\plugins\PluginSettingsDAO;

class EncryptApiKey extends Migration
{
    /** The plugin_settings.plugin_name value */
    private const PLUGIN_NAME = 'plagiarismplugin';

    /** The credential setting to encrypt */
    private const SETTING_NAME = 'ithenticateApiKey';

    /**
     * Encrypt every plaintext API key found in plugin_settings, once.
     */
    public function up(): void
    {
        $pluginSettingsDao = DAORegistry::getDAO('PluginSettingsDAO'); /** @var PluginSettingsDAO $pluginSettingsDao */

        DB::table('plugin_settings')
            ->where('plugin_name', self::PLUGIN_NAME)
            ->where('setting_name', self::SETTING_NAME)
            ->whereNotNull('setting_value')
            ->where('setting_value', '<>', '')
            ->get(['context_id', 'setting_value'])
            ->each(function (object $row) use ($pluginSettingsDao): void {
                $encrypted = self::encryptOnce($row->setting_value);

                // Already-encrypted rows come back byte-identical → nothing to write.
                if ($encrypted === $row->setting_value) {
                    return;
                }

                // Persist through the DAO (not a raw DB update) so it ALSO Cache::forget()s the stale
                // "pluginSettings-{ctx}-plagiarismplugin" entry. A raw update leaves the persistent file
                // cache (24h lifetime) serving the OLD plaintext, which — now that the field is encrypted —
                // fails app()->decrypt() and makes getSetting() return null. No install/upgrade path flushes
                // that cache, so invalidating it here is what makes every update path pick up the ciphertext.
                $pluginSettingsDao->updateSetting(
                    (int) $row->context_id,
                    self::PLUGIN_NAME,
                    self::SETTING_NAME,
                    $encrypted,
                    'string'
                );
            });
    }

    /**
     * Encrypt a value exactly once.
     *
     * A plaintext value fails to decrypt (DecryptException) and is encrypted; an already-encrypted
     * value decrypts cleanly and is returned unchanged. This decrypt-probe is what makes the
     * migration safe to re-run on every upgrade without double-wrapping the ciphertext.
     */
    public static function encryptOnce(string $value): string
    {
        try {
            Crypt::decrypt($value);
            return $value;
        } catch (DecryptException $exception) {
            return Crypt::encrypt($value);
        }
    }

    /**
     * @inheritDoc
     */
    public function down(): void
    {
        throw new DowngradeNotSupportedException();
    }
}
