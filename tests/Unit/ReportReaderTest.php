<?php

namespace Tests\Unit;

use App\Inference\HttpReportReader;
use App\Inference\LocalReportReader;
use App\Models\Issue;
use App\Models\Politician;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ReportReaderTest extends TestCase
{
    public function test_local_reader_proposes_only_cued_claims(): void
    {
        $reader = new LocalReportReader;
        $subject = new Politician(['name' => 'Sari Wibowo']);
        $subject->id = 1;
        $other = new Politician(['name' => 'Budi Santoso']);
        $other->id = 2;
        $short = new Politician(['name' => 'Budi']);
        $short->id = 3;
        $issue = new Issue(['name' => 'Subsidi pangan']);
        $text = 'Sari mendukung Budi Santoso dan menambah subsidi pangan serta peran negara.';

        $proposals = $reader->read($text, 1, [$subject, $other, $short], [$issue]);
        $kinds = collect($proposals)->map(fn ($proposal) => $proposal['kind'].':'.json_encode($proposal['payload']))->all();

        $this->assertContains('axis:'.json_encode(['axis' => 'ekonomi', 'score' => 20]), $kinds);
        $this->assertContains('stance:'.json_encode(['issue_name' => 'Subsidi pangan', 'position' => 'dukung']), $kinds);
        $this->assertContains('politician_link:'.json_encode(['other_politician_id' => 2, 'type' => 'mendukung']), $kinds);
        $this->assertCount(3, $proposals);
    }

    public function test_local_reader_ignores_a_name_without_a_cue(): void
    {
        $reader = new LocalReportReader;
        $other = new Politician(['name' => 'Budi Santoso']);
        $other->id = 2;

        $this->assertSame([], $reader->read('Budi Santoso hadir dalam rapat.', 1, [$other], []));
    }

    public function test_http_reader_drops_values_outside_the_vocabularies(): void
    {
        $reader = new HttpReportReader('https://example.test', 'key', 'model', 1, new LocalReportReader);
        $other = new Politician(['name' => 'Budi Santoso']);
        $other->id = 2;
        $issue = new Issue(['name' => 'Subsidi pangan']);

        $kept = $reader->allowed([
            ['kind' => 'axis', 'axis' => 'ekonomi', 'position' => 'negara'],
            ['kind' => 'axis', 'axis' => 'ekonomi', 'score' => 80],
            ['kind' => 'axis', 'axis' => 'ekonomi', 'position' => 'kiri'],
            ['kind' => 'axis', 'axis' => 'ekonomi', 'score' => 140],
            ['kind' => 'stance', 'issue_name' => 'isu baru', 'position' => 'dukung'],
            ['kind' => 'stance', 'issue_name' => 'subsidi pangan', 'position' => 'tolak'],
            ['kind' => 'politician_link', 'other_politician_id' => 1, 'type' => 'sekutu'],
            ['kind' => 'politician_link', 'other_politician_id' => 2, 'type' => 'lawan'],
        ], 1, [$other], [$issue]);

        $this->assertSame([
            ['kind' => 'axis', 'payload' => ['axis' => 'ekonomi', 'score' => 0]],
            ['kind' => 'axis', 'payload' => ['axis' => 'ekonomi', 'score' => 80]],
            ['kind' => 'stance', 'payload' => ['issue_name' => 'Subsidi pangan', 'position' => 'tolak']],
            ['kind' => 'politician_link', 'payload' => ['other_politician_id' => 2, 'type' => 'lawan']],
        ], $kept);
    }

    public function test_http_reader_records_the_model_when_the_call_succeeds(): void
    {
        Http::fake([
            'https://example.test/chat' => Http::response([
                'choices' => [[
                    'message' => ['content' => json_encode(['proposals' => [
                        ['kind' => 'axis', 'axis' => 'ekonomi', 'score' => 20],
                    ]])],
                ]],
            ]),
        ]);
        $reader = new HttpReportReader('https://example.test/chat', 'key', 'gpt-4o-mini', 1, new LocalReportReader);

        $proposals = $reader->read('Sari mendukung subsidi.', 1, [], []);

        $this->assertSame('http:gpt-4o-mini', $reader->method());
        $this->assertSame([['kind' => 'axis', 'payload' => ['axis' => 'ekonomi', 'score' => 20]]], $proposals);
    }

    public function test_http_reader_records_local_when_the_call_fails(): void
    {
        Http::fake(fn () => throw new ConnectionException('offline'));
        $reader = new HttpReportReader('https://example.test/chat', 'key', 'gpt-4o-mini', 1, new LocalReportReader);

        $reader->read('Sari mendukung subsidi pangan.', 1, [], []);

        $this->assertSame('local', $reader->method());
    }
}
