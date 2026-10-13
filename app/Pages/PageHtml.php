<?php

namespace App\Pages;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\MarkdownConverter;

class PageHtml
{
    public static function fromMarkdown(string $markdown): string
    {
        $environment = new Environment([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
        $environment->addExtension(new CommonMarkCoreExtension);

        return self::sanitize((new MarkdownConverter($environment))->convert($markdown)->getContent());
    }

    public static function sanitize(string $html): string
    {
        $html = strip_tags($html, '<p><br><h1><h2><h3><h4><ul><ol><li><strong><em><a><blockquote><code><pre><hr>');
        $html = preg_replace('/\s+on\w+\s*=\s*([\'"]).*?\1/i', '', $html) ?? $html;
        $html = preg_replace('/\s+style\s*=\s*([\'"]).*?\1/i', '', $html) ?? $html;

        return preg_replace_callback('/<a\b([^>]*)>/i', function (array $match) {
            $attributes = $match[1];

            if (preg_match('/href\s*=\s*([\'"])(.*?)\1/i', $attributes, $href)) {
                $url = trim($href[2]);

                if (! preg_match('~^(https?:|/|#|mailto:)~i', $url)) {
                    $attributes = preg_replace('/href\s*=\s*([\'"]).*?\1/i', 'href="#"', $attributes) ?? $attributes;
                }
            }

            return '<a'.$attributes.' rel="noreferrer">';
        }, $html) ?? $html;
    }
}
