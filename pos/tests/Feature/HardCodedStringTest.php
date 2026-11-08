<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * PLAT-1: every user-facing string lives in lang files.
 *
 * Scope: resources/views/**.blade.php and app/Livewire/**.php only. Controllers (app/Http) are
 * deliberately NOT scanned.
 *
 * Blade rules (pragmatic regex scanner, not a full parser):
 *  - Ignored: {{-- --}} and <!-- --> comments, <script>/<style>, @php ... @endphp blocks,
 *    echoes ({{ }}, {!! !!}), @directives (with or without a space before the parenthesis),
 *    HTML entities (&nbsp; &#169;), numbers/punctuation, and a bare "Rp" directly before an echo
 *    (currency symbol; show money with rupiah()).
 *  - Reported: text between tags that contains a letter.
 *  - Reported: plain tags: placeholder / title / alt / aria-label, and value on <button> and
 *    <input type=submit|button>.
 *  - Reported: <x-*> component props with a literal value, except attributes that are never
 *    shown (NON_VISIBLE, plus :bound, @event, x-*, wire:*, data-*, aria-* other than aria-label).
 *  - An attribute value is "wrapped" (OK) when it contains an echo, __(, trans(, trans_choice(
 *    or @lang(. Bound attributes (:attr, x-bind:attr) are expressions, never literal text.
 *  - Tags are matched quote-aware, so ">" or "->" inside an attribute value is safe. Echo
 *    contents are blanked first, so {{ $attributes->class([...]) }} inside a tag is safe too.
 *
 * PHP rules (token based): a prose literal (has a letter, and a space or a capital first letter;
 * lowercase keys like 'name.required' are skipped) is reported inside:
 *  - flash() / addError() / dispatch() / toast() / withMessages() arguments,
 *  - validate($rules, $messages, ...) arguments after the rules (validateOnly: after field+rules),
 *  - #[Validate(..., message: '...', as: '...')],
 *  - ->title('...') and #[Title('...')] (page titles),
 *  - ->with('key', '...') (redirect messages: the values after the key),
 *  - the body of messages() / attributes() / validationAttributes(),
 *  - the initializer of $messages / $validationAttributes properties,
 * unless wrapped in __() / trans() / trans_choice().
 */
class HardCodedStringTest extends TestCase
{
    private const FIXTURES = __DIR__.'/../fixtures/hardcoded-strings/';

    /** A whole tag, quote-aware. No capturing groups. */
    private const TAG = '<\/?[A-Za-z][\w:.-]*(?:"[^"]*"|\'[^\']*\'|[^>"\'])*>';

    /** Component (<x-*>) attributes that are never visible text. */
    private const NON_VISIBLE = [
        'class', 'id', 'name', 'type', 'href', 'src', 'for', 'method', 'action', 'icon', 'variant',
        'size', 'color', 'autocomplete', 'inputmode', 'pattern', 'role', 'target', 'rel', 'style',
        'key', 'value', 'step', 'min', 'max', 'rows', 'cols', 'width', 'height', 'tabindex',
        'loading', 'as', 'tag', 'position', 'align', 'theme', 'mode', 'lang', 'accept', 'enctype',
        'disabled', 'readonly', 'required', 'checked', 'selected', 'multiple', 'autocapitalize',
    ];

    public function test_views_and_livewire_have_no_hard_coded_strings(): void
    {
        $found = [];
        foreach ($this->files(resource_path('views'), '/\.blade\.php$/') as $f) {
            $found = [...$found, ...$this->scanBlade($f)];
        }
        foreach ($this->files(app_path('Livewire'), '/\.php$/') as $f) {
            $found = [...$found, ...$this->scanPhp($f)];
        }

        $this->assertSame([], $found, "Hard-coded strings (wrap in __()):\n".implode("\n", $found));
    }

    public function test_scanner_flags_hard_coded_blade_text(): void
    {
        $this->assertSame([
            'bad.blade.php:2: Daftar Barang',
            'bad.blade.php:4: Cari barang',
            'bad.blade.php:5: Simpan',
            'bad.blade.php:6: Logo toko',
            'bad.blade.php:8: Simpan',
            'bad.blade.php:9: Nama',
            'bad.blade.php:10: Total',
            'bad.blade.php:11: Catatan',
            'bad.blade.php:13: Barang',
        ], $this->scanBlade(self::FIXTURES.'bad.blade.php'));
    }

    public function test_scanner_passes_wrapped_blade(): void
    {
        $this->assertSame([], $this->scanBlade(self::FIXTURES.'clean.blade.php'));
    }

    public function test_scanner_flags_hard_coded_livewire_strings(): void
    {
        $this->assertSame([
            'Bad.php:8: Nama wajib',
            'Bad.php:11: Kode wajib',
            'Bad.php:15: Tersimpan',
            'Bad.php:17: Nama wajib diisi',
            'Bad.php:18: Berhasil',
            'Bad.php:19: Teks bebas satu',
            'Bad.php:20: Gagal total',
            'Bad.php:23: Teks bebas',
            'Bad.php:33: Nama barang',
            'Bad.php:36: Judul atribut',
            'Bad.php:40: Judul render',
            'Bad.php:42: Pesan redirect',
        ], $this->scanPhp(self::FIXTURES.'Bad.php'));
    }

    private function files(string $dir, string $pattern): array
    {
        if (! is_dir($dir)) {
            return [];
        }
        $out = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)) as $f) {
            if (preg_match($pattern, $f->getPathname())) {
                $out[] = $f->getPathname();
            }
        }
        sort($out);

        return $out;
    }

    /** Blank matches out but keep newlines, so line numbers survive. */
    private function blank(string $src, string $regex): string
    {
        return preg_replace_callback($regex, fn ($m) => preg_replace('/[^\n]/', ' ', $m[0]), $src);
    }

    private function line(string $src, int $offset): int
    {
        return substr_count($src, "\n", 0, $offset) + 1;
    }

    private function hasText(string $s): bool
    {
        return (bool) preg_match('/\pL/u', preg_replace('/&#?\w+;/', '', $s));
    }

    /** @param list<array{int, string}> $found */
    private function format(string $path, array $found): array
    {
        usort($found, fn ($a, $b) => [$a[0], $a[1]] <=> [$b[0], $b[1]]);

        return array_values(array_unique(array_map(fn ($f) => basename($path).':'.$f[0].': '.$f[1], $found)));
    }

    private function scanBlade(string $path): array
    {
        $src = file_get_contents($path);
        $src = $this->blank($src, '/\{\{--.*?--\}\}|<!--.*?-->|(?i:<!doctype\b[^>]*>)|<script\b.*?<\/script>|<style\b.*?<\/style>|@php\b(?!\s*\().*?@endphp/s');
        $src = preg_replace_callback('/(\{\{|\{!!)(.*?)(\}\}|!!\})/s', fn ($m) => $m[1].preg_replace('/[^\n]/', ' ', $m[2]).$m[3], $src);
        $found = [];

        preg_match_all('/'.self::TAG.'/', $src, $tags, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        foreach ($tags as $tag) {
            [$html, $at] = $tag[0];
            if (str_starts_with($html, '</')) {
                continue;
            }
            preg_match('/^<([\w:.-]+)/', $html, $n);
            $name = strtolower($n[1]);
            $component = str_starts_with($name, 'x-');
            $isButton = $name === 'button' || preg_match('/type\s*=\s*["\'](?:submit|button)["\']/i', $html);

            preg_match_all('/(?<=\s)([^\s=<>"\']+)\s*=\s*(["\'])(.*?)\2/s', $html, $attrs, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
            foreach ($attrs as $a) {
                $attr = strtolower($a[1][0]);
                [$value, $vo] = $a[3];
                if ($this->wrapped($value) || ! $this->hasText($value)) {
                    continue;
                }
                $visible = $component
                    ? ! $this->nonVisible($attr)
                    : in_array($attr, ['placeholder', 'title', 'alt', 'aria-label'], true) || ($attr === 'value' && $isButton);
                if ($visible) {
                    $found[] = [$this->line($src, $at + $vo), trim($value)];
                }
            }
        }

        $text = $this->blank($src, '/\bRp(?=\{\{|\{!!)|'.self::TAG.'|\{\{.*?\}\}|\{!!.*?!!\}|(?<![\w@])@\w+(?:[ \t]*(\((?:[^()]|(?1))*\)))?|&#?\w+;/s');
        foreach (preg_split('/\R/', $text) as $i => $ln) {
            if (preg_match('/\pL/u', $ln)) {
                $found[] = [$i + 1, trim($ln)];
            }
        }

        return $this->format($path, $found);
    }

    private function wrapped(string $v): bool
    {
        foreach (['{{', '{!!', '__(', 'trans(', 'trans_choice(', '@lang('] as $w) {
            if (str_contains($v, $w)) {
                return true;
            }
        }

        return false;
    }

    private function nonVisible(string $attr): bool
    {
        if ($attr === 'aria-label') {
            return false;
        }
        foreach ([':', '@', 'x-', 'wire:', 'data-', 'aria-'] as $p) {
            if (str_starts_with($attr, $p)) {
                return true;
            }
        }

        return in_array($attr, self::NON_VISIBLE, true);
    }

    private function scanPhp(string $path): array
    {
        $t = [];
        foreach (token_get_all(file_get_contents($path)) as $tok) {
            if (is_array($tok)) {
                if (! in_array($tok[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                    $t[] = [$tok[0], $tok[1], $tok[2]];
                }
            } else {
                $t[] = [$tok, $tok, end($t)[2] ?? 1];
            }
        }

        $always = fn () => true;
        $found = [];
        $depth = 0;
        $blank = [null, '', 0];
        foreach ($t as $i => [$id, $x]) {
            $prev = $t[$i - 1] ?? $blank;
            $next = $t[$i + 1] ?? $blank;
            $lower = strtolower($x);

            if ($this->opens($id, $x) && $x === '{' || $id === T_CURLY_OPEN || $id === T_DOLLAR_OPEN_CURLY_BRACES) {
                $depth++;
            } elseif (is_string($id) && $x === '}') {
                $depth--;
            }

            $range = null; // [from, to, keep]
            if ($id === T_STRING && $prev[0] === T_FUNCTION && in_array($lower, ['messages', 'attributes', 'validationattributes'], true)) {
                for ($j = $i + 1; isset($t[$j]) && ! (is_string($t[$j][0]) && in_array($t[$j][1], ['{', ';'], true)); $j++);
                if (isset($t[$j]) && $t[$j][1] === '{') {
                    $range = [$j + 1, $this->closer($t, $j) - 1, $always];
                }
            } elseif ($id === T_VARIABLE && in_array($x, ['$messages', '$validationAttributes'], true) && $next[1] === '=' && $depth === 1) {
                for ($j = $i; isset($t[$j]) && $t[$j][1] !== ';'; $j++);
                $range = [$i + 2, $j - 1, $always];
            } elseif (in_array($id, [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true) && $next[1] === '(') {
                $end = null;
                if ($prev[0] === T_ATTRIBUTE && str_ends_with($lower, 'validate')) {
                    $keep = fn ($arg, $argName) => in_array($argName, ['message', 'as'], true);
                } elseif ($prev[0] === T_ATTRIBUTE && str_ends_with($lower, 'title')) {
                    $keep = $always;
                } elseif ($prev[0] === T_FUNCTION || $prev[0] === T_NEW) {
                    $keep = null;
                } elseif (in_array($lower, ['flash', 'adderror', 'dispatch', 'toast', 'withmessages'], true)) {
                    $keep = $always;
                } elseif ($lower === 'title') {
                    $keep = $always;
                } elseif ($lower === 'with') {
                    $keep = fn ($arg) => $arg >= 1;
                } elseif ($lower === 'validate') {
                    $keep = fn ($arg) => $arg >= 1;
                } elseif ($lower === 'validateonly') {
                    $keep = fn ($arg) => $arg >= 2;
                } else {
                    $keep = null;
                }
                if ($keep) {
                    $range = [$i + 2, $this->closer($t, $i + 1) - 1, $keep];
                }
            }

            if ($range) {
                foreach ($this->literals($t, ...$range) as $f) {
                    $found[] = $f;
                }
            }
        }

        return $this->format($path, $found);
    }

    private function opens(mixed $id, string $x): bool
    {
        return is_string($id) && in_array($x, ['(', '[', '{'], true);
    }

    /** Index of the bracket closing the opener at $i. */
    private function closer(array $t, int $i): int
    {
        $d = 0;
        for ($n = count($t); $i < $n; $i++) {
            [$id, $x] = $t[$i];
            if ($this->opens($id, $x) || $id === T_CURLY_OPEN || $id === T_DOLLAR_OPEN_CURLY_BRACES) {
                $d++;
            } elseif (is_string($id) && in_array($x, [')', ']', '}'], true) && --$d === 0) {
                return $i;
            }
        }

        return $n - 1;
    }

    /** Prose literals in tokens $from..$to; $keep(topLevelArgIndex, namedArg) filters them. */
    private function literals(array $t, int $from, int $to, callable $keep): array
    {
        $out = [];
        $d = 0;
        $arg = 0;
        $argName = null;
        $start = true;
        for ($i = $from; $i <= $to; $i++) {
            [$id, $x, $ln] = $t[$i];

            if (is_string($id) && $x === ',' && $d === 0) {
                $arg++;
                $argName = null;
                $start = true;

                continue;
            }
            if ($d === 0 && $start && $id === T_STRING && ($t[$i + 1][1] ?? '') === ':') {
                $argName = $x;
                $start = false;

                continue;
            }
            $start = false;

            if ($this->opens($id, $x) || $id === T_CURLY_OPEN || $id === T_DOLLAR_OPEN_CURLY_BRACES) {
                $d++;
            } elseif (is_string($id) && in_array($x, [')', ']', '}'], true)) {
                $d--;
            } elseif ($id === T_STRING && in_array(strtolower($x), ['__', 'trans', 'trans_choice'], true) && ($t[$i + 1][1] ?? '') === '(') {
                $i = $this->closer($t, $i + 1); // wrapped: skip the whole call
            } elseif ($id === T_CONSTANT_ENCAPSED_STRING || $id === T_ENCAPSED_AND_WHITESPACE) {
                $v = trim($id === T_CONSTANT_ENCAPSED_STRING ? substr($x, 1, -1) : $x);
                if ($v !== '' && preg_match('/\pL/u', $v) && (str_contains($v, ' ') || ctype_upper($v[0])) && $keep($arg, $argName)) {
                    $out[] = [$ln, $v];
                }
            }
        }

        return $out;
    }
}
