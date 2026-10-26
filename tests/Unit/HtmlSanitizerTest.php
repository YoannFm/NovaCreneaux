<?php

namespace Tests\Unit;

use App\Support\HtmlSanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class HtmlSanitizerTest extends TestCase
{
    /** Ce qui ne doit jamais subsister, en minuscules, dans le resultat. */
    private const FORBIDDEN = ['<script', 'onerror', 'onclick', 'onload', 'ontoggle', 'onfocus', 'javascript:', '<iframe', '<form', '<svg', '<math', '<style', '<object', '<embed', '<meta', '<base', '<link', 'expression(', 'url(', 'data:text', 'position: fixed', ' id=', ' name='];

    #[DataProvider('attacks')]
    public function test_dangerous_html_is_neutralised(string $input): void
    {
        $output = strtolower(HtmlSanitizer::clean($input));

        foreach (self::FORBIDDEN as $needle) {
            $this->assertStringNotContainsString($needle, $output, "« {$needle} » subsiste dans : {$output}");
        }
    }

    public static function attacks(): array
    {
        return array_map(fn (string $html) => [$html], [
            '<script>alert(1)</script>',
            '<p onclick="alert(1)">x</p>',
            '<img src=x onerror=alert(1)>',
            '<a href="javascript:alert(1)">x</a>',
            '<a href="jav&#x09;ascript:alert(1)">x</a>',
            '<a href=" javascript:alert(1)">x</a>',
            '<a href="JaVaScRiPt:alert(1)">x</a>',
            '<a href="data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==">x</a>',
            '<svg onload=alert(1)><script>alert(1)</script></svg>',
            '<math><mtext><table><mglyph><style><img src=x onerror=alert(1)>',
            '<noscript><p title="</noscript><img src=x onerror=alert(1)>">',
            '<iframe src="https://evil.example"></iframe>',
            '<form action="https://evil.example"><input name=x></form>',
            '<object data="x"></object><embed src="x">',
            '<meta http-equiv="refresh" content="0;url=https://evil.example">',
            '<base href="https://evil.example/">',
            '<link rel="stylesheet" href="https://evil.example/x.css">',
            '<style>body{display:none}</style>',
            '<p style="background:url(javascript:alert(1))">x</p>',
            '<p style="color:expression(alert(1))">x</p>',
            '<p style="position:fixed;top:0;left:0;width:100%;height:100%">x</p>',
            '<details open ontoggle=alert(1)>x</details>',
            '<input autofocus onfocus=alert(1)>',
            '<a id="admin-theme-toggle" name="x">x</a>',
            '<p><script>alert(1)</script><p>',
            '<<script>script>alert(1)<</script>/script>',
            '<img src="x" onerror="alert(1)" />',
            "<a href=\"\x01javascript:alert(1)\">x</a>",
            '<p style="font-family:\'a\';b:c">x</p>',
        ]);
    }

    public function test_the_formatting_an_editor_produces_is_kept(): void
    {
        $html = '<h2>Titre</h2><p style="text-align: center; color: #ff0000">Texte <strong>gras</strong>, <em>italique</em> et <u>souligné</u>.</p>'
            . '<ul><li>un</li><li>deux</li></ul><ol><li>a</li></ol><blockquote>cité</blockquote>'
            . '<table><thead><tr><th>A</th></tr></thead><tbody><tr><td colspan="2">b</td></tr></tbody></table>'
            . '<p><a href="https://exemple.fr/page" target="_blank">lien</a> <a href="/p/regles">interne</a> <a href="mailto:cdi@exemple.fr">écrire</a></p>'
            . '<p><img src="https://exemple.fr/a.png" alt="photo" width="120" /></p>';

        $clean = HtmlSanitizer::clean($html);

        foreach (['<h2>Titre</h2>', 'text-align: center', 'color: #ff0000', '<strong>gras</strong>', '<em>italique</em>', '<li>deux</li>', '<blockquote>cité</blockquote>', 'colspan="2"', 'href="https://exemple.fr/page"', 'href="/p/regles"', 'href="mailto:cdi&#64;exemple.fr"', 'src="https://exemple.fr/a.png"', 'alt="photo"', 'width="120"'] as $expected) {
            $this->assertStringContainsString($expected, $clean);
        }
    }

    public function test_external_links_cannot_reach_back_to_the_site(): void
    {
        $clean = HtmlSanitizer::clean('<a href="https://evil.example" target="_blank">x</a>');

        $this->assertStringContainsString('rel="noopener noreferrer"', $clean);
    }

    public function test_only_safe_style_properties_survive(): void
    {
        $clean = HtmlSanitizer::clean('<p style="color: red; behavior: url(x.htc); background-image: url(https://evil.example/t.png); text-align: right; margin: 0 auto">x</p>');

        $this->assertSame('<p style="color: red; text-align: right">x</p>', $clean);
    }

    public function test_empty_and_null_inputs_give_an_empty_string(): void
    {
        $this->assertSame('', HtmlSanitizer::clean(null));
        $this->assertSame('', HtmlSanitizer::clean(''));
        $this->assertSame('', HtmlSanitizer::clean("  \n "));
    }

    public function test_cleaning_twice_changes_nothing(): void
    {
        $once = HtmlSanitizer::clean('<p style="color:red" onclick="x">a <a href="https://e.fr" target="_blank">b</a></p><script>x</script>');

        $this->assertSame($once, HtmlSanitizer::clean($once));
    }

    public function test_long_legitimate_pages_are_not_truncated(): void
    {
        $html = str_repeat('<p>Un paragraphe de texte assez long pour un règlement intérieur.</p>', 1000);

        $this->assertSame(1000, substr_count(HtmlSanitizer::clean($html), '</p>'));
    }
}
