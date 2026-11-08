<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Lang;
use Tests\TestCase;

/**
 * PLAT-1: every literal key passed to __(), trans(), trans_choice() or @lang() has an Indonesian
 * translation. Plain-text keys must be in lang/id.json; dotted group keys (validation.required)
 * must resolve in lang/id/<group>.php. Keys with variables are skipped.
 */
class TranslationKeysTest extends TestCase
{
    private const FIXTURES = __DIR__.'/../fixtures/translation-keys/';

    public function test_every_literal_translation_key_exists_in_indonesian(): void
    {
        $missing = [];
        foreach ([app_path(), resource_path('views'), database_path()] as $dir) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)) as $f) {
                if (str_ends_with($f->getPathname(), '.php')) {
                    $missing = [...$missing, ...$this->missingKeys($f->getPathname())];
                }
            }
        }

        $this->assertSame([], array_values(array_unique($missing)), "Untranslated keys (add to lang/id.json or lang/id/*.php):\n".implode("\n", array_unique($missing)));
    }

    public function test_scanner_reports_untranslated_keys_only(): void
    {
        $this->assertSame([
            'Missing.blade.php: Belum diterjemahkan',
            'Missing.blade.php: Juga belum',
            'Missing.blade.php: validation.tidak_ada',
            'Missing.blade.php: Satu barang|:count barang',
            "Missing.blade.php: Kunci 'kutip' hilang",
        ], $this->missingKeys(self::FIXTURES.'Missing.blade.php'));
    }

    /** @return list<string> */
    private function missingKeys(string $path): array
    {
        $src = file_get_contents($path);
        preg_match_all('/(?<![\w>$])(?:__|trans|trans_choice)\(\s*(?:\'((?:[^\'\\\\]|\\\\.)*)\'|"((?:[^"\\\\]|\\\\.)*)")|@lang\(\s*(?:\'((?:[^\'\\\\]|\\\\.)*)\'|"((?:[^"\\\\]|\\\\.)*)")/', $src, $m, PREG_SET_ORDER);

        $out = [];
        foreach ($m as $hit) {
            $key = stripslashes(implode('', array_slice($hit, 1)));
            if (str_contains($key, '$') || ($this->isGroupKey($key) ? Lang::has($key, 'id', false) : $this->inJson($key))) {
                continue;
            }
            $out[] = basename($path).': '.$key;
        }

        return $out;
    }

    private function isGroupKey(string $key): bool
    {
        return (bool) preg_match('/^[a-z_]+(\.[\w*-]+)+$/', $key);
    }

    private function inJson(string $key): bool
    {
        static $json;
        $json ??= json_decode(file_get_contents(base_path('lang/id.json')), true);

        return array_key_exists($key, $json);
    }
}
