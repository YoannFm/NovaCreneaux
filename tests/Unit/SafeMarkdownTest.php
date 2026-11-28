<?php

namespace Tests\Unit;

use App\Support\SafeMarkdown;
use Tests\TestCase;

class SafeMarkdownTest extends TestCase
{
    public function test_markdown_is_rendered(): void
    {
        $html = SafeMarkdown::toHtml("## Nouveautes\n\n- **Gras** et `code`\n- Deuxieme point");

        $this->assertStringContainsString('<h2>Nouveautes</h2>', $html);
        $this->assertStringContainsString('<strong>Gras</strong>', $html);
        $this->assertStringContainsString('<li>Deuxieme point</li>', $html);
    }

    public function test_bare_links_are_clickable_and_external_ones_are_safe(): void
    {
        $html = SafeMarkdown::toHtml('**Full Changelog**: https://github.com/YoannFm/NovaCreneaux/compare/v1.0.0...v1.0.1');

        $this->assertStringContainsString('href="https://github.com/YoannFm/NovaCreneaux/compare/v1.0.0...v1.0.1"', $html);
        $this->assertStringContainsString('rel="nofollow noopener noreferrer"', $html);
        $this->assertStringContainsString('target="_blank"', $html);
    }

    public function test_raw_html_is_escaped(): void
    {
        $html = SafeMarkdown::toHtml('<script>alert(1)</script> <img src=x onerror=alert(1)>');

        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_unsafe_link_schemes_are_removed(): void
    {
        $html = SafeMarkdown::toHtml("[clic](javascript:alert(1)) [data](data:text/html;base64,AAAA) [ok](https://example.com)");

        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('data:text', $html);
        $this->assertStringContainsString('href="https://example.com"', $html);
    }
}
