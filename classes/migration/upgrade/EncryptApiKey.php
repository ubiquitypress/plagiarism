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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use PKP\install\DowngradeNotSupportedException;

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
        DB::table('plugin_settings')
            ->where('plugin_name', self::PLUGIN_NAME)
            ->where('setting_name', self::SETTING_NAME)
            ->whereNotNull('setting_value')
            ->where('setting_value', '<>', '')
            ->get(['context_id', 'setting_value'])
            ->each(function (object $row): void {
                $encrypted = self::encryptOnce($row->setting_value);

                // Already-encrypted rows come back byte-identical → nothing to write.
                if ($encrypted === $row->setting_value) {
                    return;
                }

                $contextId = (int) $row->context_id;

                DB::table('plugin_settings')
                    ->where('context_id', $row->context_id)
                    ->where('plugin_name', self::PLUGIN_NAME)
                    ->where('setting_name', self::SETTING_NAME)
                    ->update(['setting_value' => $encrypted]);

                // PluginSettingsDAO caches each plugin+context in the persistent file store (24h lifetime)
                // and only forgets it from updateSetting()/deleteSetting(). Need manula clearing
                Cache::forget("pluginSettings-{$contextId}-" . self::PLUGIN_NAME);
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
