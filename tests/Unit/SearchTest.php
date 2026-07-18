<?php

namespace JordJD\RedditSearch\Tests;

use JordJD\BaseSearch\Interfaces\SearchResultInterface;
use JordJD\RedditSearch\RedditSearcher;
use JordJD\RedditSearch\RedditSearchResult;
use PHPUnit\Framework\TestCase;

class SearchTest extends TestCase
{
    public function testAuthenticatedSearchBuildsOptionsAndNormalizesResults()
    {
        $request = [];
        $client = function ($url, $headers, $timeout) use (&$request) {
            $request = compact('url', 'headers', 'timeout');

            return json_encode([
                'data' => [
                    'after' => 't3_nextpage',
                    'children' => [
                        ['data' => [
                            'title' => 'PHP &amp; Reddit',
                            'url' => 'https://example.com/php',
                            'permalink' => '/r/PHP/comments/abc123/example/',
                            'selftext' => 'A useful post.',
                            'link_flair_text' => null,
                            'subreddit' => 'PHP',
                            'author' => 'example-user',
                        ]],
                        ['data' => [
                            'title' => 'Second result',
                            'url' => 'https://example.com/second',
                            'permalink' => '/r/PHP/comments/def456/second/',
                            'selftext' => '',
                            'link_flair_text' => 'Discussion',
                            'subreddit' => 'PHP',
                            'author' => 'another-user',
                        ]],
                    ],
                ],
            ]);
        };

        $searcher = new RedditSearcher('token', 'php:test:v1.0 (by /u/test)', $client, 9);
        $results = $searcher->search(' PHP arrays ', [
            'after' => 't3_abc123',
            'include_over_18' => false,
            'limit' => 2,
            'sort' => 'top',
            'subreddit' => 'PHP',
            'time' => 'year',
        ]);

        $this->assertCount(2, $results);
        $this->assertInstanceOf(RedditSearchResult::class, $results[0]);
        $this->assertInstanceOf(SearchResultInterface::class, $results[0]);
        $this->assertSame('PHP & Reddit', $results[0]->getTitle());
        $this->assertSame('A useful post.', $results[0]->getDescription());
        $this->assertSame('https://example.com/php', $results[0]->getUrl());
        $this->assertSame('https://www.reddit.com/r/PHP/comments/abc123/example/', $results[0]->getPermalink());
        $this->assertSame('PHP', $results[0]->getSubreddit());
        $this->assertSame('example-user', $results[0]->getAuthor());
        $this->assertSame(1.0, $results[0]->getScore());
        $this->assertSame(0.5, $results[1]->getScore());
        $this->assertSame('Discussion', $results[1]->getDescription());
        $this->assertEquals($results[0]->toArray(), json_decode(json_encode($results[0]), true));
        $this->assertSame('t3_nextpage', $searcher->getAfter());

        $this->assertSame(9, $request['timeout']);
        $this->assertContains('Authorization: Bearer token', $request['headers']);
        $this->assertContains('User-Agent: php:test:v1.0 (by /u/test)', $request['headers']);
        $this->assertStringStartsWith('https://oauth.reddit.com/r/PHP/search?', $request['url']);
        $this->assertStringContainsString('q=PHP%20arrays', $request['url']);
        $this->assertStringContainsString('after=t3_abc123', $request['url']);
        $this->assertStringContainsString('include_over_18=false', $request['url']);
        $this->assertStringContainsString('restrict_sr=true', $request['url']);
    }

    public function testEmptyAndMalformedResponsesAreHandledClearly()
    {
        $empty = new RedditSearcher('token', 'agent', function () {
            return '{"data":{"children":[]}}';
        });
        $this->assertSame([], $empty->search('nothing'));

        $invalidJson = new RedditSearcher('token', 'agent', function () {
            return 'not json';
        });

        try {
            $invalidJson->search('test');
            $this->fail('Invalid Reddit JSON should throw.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('invalid JSON', $exception->getMessage());
        }
    }

    public function testInvalidSearchInputFailsBeforeHttp()
    {
        $cases = [
            [' ', [], 'cannot be empty'],
            ['php', ['unknown' => true], 'Unsupported'],
            ['php', ['limit' => 0], 'between 1 and 100'],
            ['php', ['sort' => 'oldest'], 'sort'],
            ['php', ['time' => 'decade'], 'time range'],
            ['php', ['after' => 'abc123'], 'fullname'],
            ['php', ['include_over_18' => 'no'], 'boolean'],
            ['php', ['subreddit' => 'not/a/subreddit'], 'subreddit'],
        ];

        foreach ($cases as $case) {
            $called = false;
            $searcher = new RedditSearcher('token', 'agent', function () use (&$called) {
                $called = true;
                return '{}';
            });

            try {
                $searcher->search($case[0], $case[1]);
                $this->fail('Invalid search input should throw.');
            } catch (\InvalidArgumentException $exception) {
                $this->assertStringContainsString($case[2], $exception->getMessage());
            }

            $this->assertFalse($called);
        }
    }

    public function testConstructorValidatesCredentialsAndTimeout()
    {
        foreach ([
            ['', 'agent', 5, 'access token'],
            ["token\r\ninjected", 'agent', 5, 'line breaks'],
            ['token', '', 5, 'user agent'],
            ['token', "agent\r\ninjected", 5, 'line breaks'],
            ['token', 'agent', 0, 'timeout'],
        ] as $case) {
            try {
                new RedditSearcher($case[0], $case[1], null, $case[2]);
                $this->fail('Invalid constructor input should throw.');
            } catch (\InvalidArgumentException $exception) {
                $this->assertStringContainsString($case[3], strtolower($exception->getMessage()));
            }
        }
    }
}
