{!! '<'.'?xml version="1.0" encoding="UTF-8"?>'."\n" !!}
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
<channel>
    <title>{{ config('app.name', 'PolitiSEE') }}</title>
    <link>{{ $baseUrl }}</link>
    <description>Gudang data profil pejabat dan politisi. Setiap catatan yang terbit menaut ke sumber publik.</description>
    <language>id</language>
    <atom:link href="{{ $baseUrl }}/feed" rel="self" type="application/rss+xml"/>
    @php
        $latest = $records->first();
        $latestAt = $latest ? ($latest->reviewed_at ?? $latest->created_at) : now();
    @endphp
    <lastBuildDate>{{ $latestAt->format('D, d M Y H:i:s O') }}</lastBuildDate>
    @foreach ($records as $record)
        @php
            $publishedAt = $record->reviewed_at ?? $record->created_at;
            $label = $labels[$record->category] ?? $record->category;
            $profileUrl = $baseUrl.'/politicians/'.$record->politician_id;
            $party = $record->politician->party->name ?? null;
            $lines = [];
            if ($party) {
                $lines[] = 'Partai: '.$party;
            }
            if ($record->quote) {
                $lines[] = 'Kutipan: '.$record->quote;
            }
            if ($record->summary) {
                $lines[] = 'Ringkasan: '.$record->summary;
            }
            $lines[] = 'Sumber: '.$record->source_url;
            if ($record->category === \App\Models\PublicRecord::CATEGORY_ALLEGATION) {
                $lines[] = 'Dugaan bukan vonis. Catatan ini mencatat dugaan, bukan putusan pengadilan.';
            }
        @endphp
        <item>
            <title>{{ $record->politician->name }} — {{ $label }}</title>
            <link>{{ $profileUrl }}</link>
            <guid isPermaLink="false">{{ $profileUrl }}#record-{{ $record->id }}</guid>
            <pubDate>{{ $publishedAt->format('D, d M Y H:i:s O') }}</pubDate>
            <category>{{ $label }}</category>
            <description>{{ implode("\n", $lines) }}</description>
        </item>
    @endforeach
</channel>
</rss>
