<?php

namespace Tests\Unit;

use App\Search\HashingEmbedder;
use PHPUnit\Framework\TestCase;

class HashingEmbedderTest extends TestCase
{
    public function test_related_text_is_closer_than_unrelated_text(): void
    {
        $embedder = new HashingEmbedder(256);
        $koruptor = $embedder->embed('koruptor');
        $related = $embedder->maxWordCosine(
            $koruptor,
            'koruptor',
            'Terdakwa divonis karena korupsi anggaran bansos'
        );
        $unrelated = $embedder->maxWordCosine(
            $koruptor,
            'koruptor',
            'jadwal pertandingan sepak bola malam ini'
        );

        $this->assertGreaterThan($embedder->semanticThreshold(), $related);
        $this->assertGreaterThan($unrelated, $related);
    }
}
