<?php

namespace JordJD\RedditSearch\Tests;

use JordJD\BaseSearch\Interfaces\SearchResultInterface;
use JordJD\RedditSearch\RedditSearcher;
use JordJD\RedditSearch\RedditSearchResult;
use PHPUnit\Framework\TestCase;

final class SearchTest extends TestCase
{
    public function testSearch()
    {
        $searcher = new RedditSearcher();
        try {
            $results = $searcher->search('PHP programming language');
        } catch (\Throwable $e) {
            $this->markTestSkipped('Reddit API unavailable: '.$e->getMessage());
            return;
        }

        if (count($results) === 0) {
            $this->markTestSkipped('Reddit API returned zero results.');
            return;
        }

        $this->assertGreaterThanOrEqual(1, count($results));

        foreach($results as $result) {
            $this->assertTrue($result instanceof RedditSearchResult);
            $this->assertTrue($result instanceof SearchResultInterface);

            $this->assertGreaterThanOrEqual(0, $result->getScore());
            $this->assertLessThanOrEqual(1, $result->getScore());
        }
    }

}
