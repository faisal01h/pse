<?php

namespace App\Plugins\NewsRadar;

use Illuminate\Support\Facades\Http;
use Throwable;

class RssFeedReader
{
    /**
     * @return list<array{guid: string, link: string, title: string, summary: string}>
     */
    public function fetch(string $feedUrl): array
    {
        try {
            $body = Http::timeout(30)
                ->withHeaders(['User-Agent' => 'PolitiSEE-NewsRadar/1.0'])
                ->get($feedUrl)
                ->throw()
                ->body();
        } catch (Throwable) {
            return [];
        }

        return $this->parse($body, $feedUrl);
    }

    /**
     * @return list<array{guid: string, link: string, title: string, summary: string}>
     */
    public function parse(string $xml, string $feedUrl = ''): array
    {
        $previous = libxml_use_internal_errors(true);
        try {
            $document = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if ($document === false) {
            return [];
        }

        $items = [];

        if (isset($document->channel->item)) {
            foreach ($document->channel->item as $item) {
                $items[] = $this->normalizeRssItem($item, $feedUrl);
            }
        } elseif (isset($document->entry)) {
            foreach ($document->entry as $entry) {
                $items[] = $this->normalizeAtomEntry($entry, $feedUrl);
            }
        }

        return array_values(array_filter($items, fn ($row) => $row['guid'] !== '' || $row['link'] !== ''));
    }

    /**
     * @return array{guid: string, link: string, title: string, summary: string}
     */
    private function normalizeRssItem(object $item, string $feedUrl): array
    {
        $link = trim((string) ($item->link ?? ''));
        $guid = trim((string) ($item->guid ?? $link));
        if ($guid === '') {
            $guid = $link !== '' ? $link : md5($feedUrl.'|'.(string) ($item->title ?? ''));
        }

        return [
            'guid' => mb_substr($guid, 0, 512),
            'link' => mb_substr($link, 0, 2048),
            'title' => mb_substr(trim((string) ($item->title ?? '')), 0, 512),
            'summary' => trim(strip_tags((string) ($item->description ?? $item->summary ?? ''))),
        ];
    }

    /**
     * @return array{guid: string, link: string, title: string, summary: string}
     */
    private function normalizeAtomEntry(object $entry, string $feedUrl): array
    {
        $link = '';
        if (isset($entry->link)) {
            foreach ($entry->link as $node) {
                $attrs = $node->attributes();
                $href = trim((string) ($attrs['href'] ?? ''));
                $rel = (string) ($attrs['rel'] ?? 'alternate');
                if ($href !== '' && ($rel === 'alternate' || $link === '')) {
                    $link = $href;
                    if ($rel === 'alternate') {
                        break;
                    }
                }
            }
        }

        $guid = trim((string) ($entry->id ?? $link));
        if ($guid === '') {
            $guid = $link !== '' ? $link : md5($feedUrl.'|'.(string) ($entry->title ?? ''));
        }

        return [
            'guid' => mb_substr($guid, 0, 512),
            'link' => mb_substr($link, 0, 2048),
            'title' => mb_substr(trim((string) ($entry->title ?? '')), 0, 512),
            'summary' => trim(strip_tags((string) ($entry->summary ?? $entry->content ?? ''))),
        ];
    }
}
