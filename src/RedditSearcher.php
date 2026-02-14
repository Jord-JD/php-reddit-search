<?php

namespace JordJD\RedditSearch;

use JordJD\BaseSearch\Interfaces\SearcherInterface;

class RedditSearcher implements SearcherInterface
{
    const URL = 'https://www.reddit.com/search/.json?q=[QUERY]';
    private const USER_AGENT = 'jord-jd-reddit-search/1.0 (+https://github.com/Jord-JD/php-reddit-search)';

    public function search(string $query): array
    {
        $url = $this->buildUrl($query);

        $response = $this->fetch($url);
        $decodedResponse = json_decode($response, true);
        if (!is_array($decodedResponse) || !isset($decodedResponse['data']['children']) || !is_array($decodedResponse['data']['children'])) {
            return [];
        }

        $results = [];

        $count = count($decodedResponse['data']['children']);
        if ($count === 0) {
            return [];
        }

        foreach ($decodedResponse['data']['children'] as $index => $item) {
            $score = ($count - $index) / $count;
            $results[] = new RedditSearchResult($item, $score);
        }

        return $results;
    }

    private function fetch(string $url): string
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 5,
                'header' => "User-Agent: " . self::USER_AGENT . "\r\n",
            ],
        ]);

        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            throw new \RuntimeException('Unable to fetch Reddit search results.');
        }

        return $response;
    }

    private function buildUrl(string $query): string
    {
        return str_replace(
            ['[QUERY]'],
            [urlencode($query)],
            self::URL
        );
    }
}
