<?php

namespace Tests\Concerns;

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Static WCAG checks on rendered HTML. Not a replacement for testing with a screen reader,
 * but it stops the common regressions: missing alt text, unlabelled fields, unnamed buttons
 * and links, duplicate ids, missing page language or title, skipped heading levels.
 */
trait ChecksAccessibility
{
    protected function assertAccessibleHtml(string $html, string $page): void
    {
        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML($html, LIBXML_NONET);
        libxml_clear_errors();
        $xp = new DOMXPath($doc);
        $problems = [];

        if (! $xp->query('//html[@lang and string-length(@lang) > 1]')->length) {
            $problems[] = 'The <html> element has no lang attribute.';
        }
        if (trim((string) $xp->query('//title')->item(0)?->textContent) === '') {
            $problems[] = 'The page has no title.';
        }
        $h1 = $xp->query('//h1')->length;
        if ($h1 !== 1) {
            $problems[] = "The page has {$h1} h1 headings (expected 1).";
        }

        $previous = 0;
        foreach ($xp->query('//h1|//h2|//h3|//h4|//h5|//h6') as $h) {
            $level = (int) substr($h->nodeName, 1);
            if ($previous && $level > $previous + 1) {
                $problems[] = "Heading level skips from h{$previous} to h{$level}: “".trim($h->textContent).'”';
            }
            $previous = $level;
        }

        foreach ($xp->query('//img') as $img) {
            if (! $img->hasAttribute('alt')) {
                $problems[] = 'Image without alt: '.$img->getAttribute('src');
            }
        }

        $labelled = [];
        foreach ($xp->query('//label[@for]') as $label) {
            $labelled[$label->getAttribute('for')] = true;
        }
        $controls = $xp->query('//input[not(@type="hidden") and not(@type="submit") and not(@type="button")]|//select|//textarea');
        foreach ($controls as $c) {
            /** @var DOMElement $c */
            $ok = ($c->getAttribute('id') && isset($labelled[$c->getAttribute('id')]))
                || $c->hasAttribute('aria-label') || $c->hasAttribute('aria-labelledby')
                || $xp->query('ancestor::label', $c)->length > 0;
            if (! $ok) {
                $problems[] = 'Form field without a label: '.$c->nodeName.' name="'.$c->getAttribute('name').'"';
            }
        }

        foreach ($xp->query('//button') as $b) {
            if (trim($b->textContent) === '' && ! $b->hasAttribute('aria-label')) {
                $problems[] = 'Button without a name.';
            }
        }
        foreach ($xp->query('//a[@href]') as $a) {
            $name = trim($a->textContent).$a->getAttribute('aria-label');
            foreach ($xp->query('.//img[@alt]', $a) as $img) {
                $name .= $img->getAttribute('alt');
            }
            if (trim($name) === '') {
                $problems[] = 'Link without a name: '.$a->getAttribute('href');
            }
        }

        $ids = [];
        foreach ($xp->query('//*[@id]') as $el) {
            $ids[$el->getAttribute('id')] = ($ids[$el->getAttribute('id')] ?? 0) + 1;
        }
        foreach (array_filter($ids, fn ($n) => $n > 1) as $id => $n) {
            $problems[] = "Duplicate id “{$id}” ({$n} times).";
        }

        foreach ($xp->query('//*[@tabindex]') as $el) {
            if ((int) $el->getAttribute('tabindex') > 0) {
                $problems[] = 'Positive tabindex on '.$el->nodeName;
            }
        }

        $this->assertSame([], $problems, "Accessibility problems on {$page}:\n- ".implode("\n- ", $problems));
    }
}
