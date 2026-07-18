<?php

namespace JordJD\RedditSearch;

use JordJD\BaseSearch\Interfaces\SearcherInterface;

class RedditSearcher implements SearcherInterface
{
    const URL = 'https://oauth.reddit.com';

    /** @var string */
    private $accessToken;

    /** @var string */
    private $userAgent;

    /** @var callable|null */
    private $httpClient;

    /** @var int */
    private $timeoutSeconds;

    /** @var array<string, float|int|null> */
    private $lastRateLimit = [
        'used' => null,
        'remaining' => null,
        'reset' => null,
    ];

    /** @var string|null */
    private $after;

    public function __construct(
        string $accessToken,
        string $userAgent,
        callable $httpClient = null,
        int $timeoutSeconds = 5
    ) {
        if (trim($accessToken) === '') {
            throw new \InvalidArgumentException('A Reddit OAuth access token is required.');
        }

        if (preg_match('/[\r\n]/', $accessToken)) {
            throw new \InvalidArgumentException('The Reddit OAuth access token cannot contain line breaks.');
        }

        if (trim($userAgent) === '') {
            throw new \InvalidArgumentException('A unique descriptive Reddit user agent is required.');
        }

        if (preg_match('/[\r\n]/', $userAgent)) {
            throw new \InvalidArgumentException('The Reddit user agent cannot contain line breaks.');
        }

        if ($timeoutSeconds < 1) {
            throw new \InvalidArgumentException('The HTTP timeout must be at least one second.');
        }

        $this->accessToken = trim($accessToken);
        $this->userAgent = trim($userAgent);
        $this->httpClient = $httpClient;
        $this->timeoutSeconds = $timeoutSeconds;
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return RedditSearchResult[]
     */
    public function search(string $query, array $options = []): array
    {
        $query = trim($query);

        if ($query === '') {
            throw new \InvalidArgumentException('The Reddit search query cannot be empty.');
        }

        $url = $this->buildUrl($query, $options);
        $headers = [
            'Accept: application/json',
            'Authorization: Bearer '.$this->accessToken,
            'User-Agent: '.$this->userAgent,
        ];

        $response = $this->fetch($url, $headers);
        $decodedResponse = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Reddit returned invalid JSON: '.json_last_error_msg());
        }

        if (!is_array($decodedResponse) || !isset($decodedResponse['data']['children']) || !is_array($decodedResponse['data']['children'])) {
            throw new \RuntimeException('Reddit returned an unexpected search response.');
        }

        $this->after = isset($decodedResponse['data']['after']) && is_string($decodedResponse['data']['after'])
            ? $decodedResponse['data']['after']
            : null;

        $results = [];
        $count = count($decodedResponse['data']['children']);

        if ($count === 0) {
            return [];
        }

        foreach ($decodedResponse['data']['children'] as $index => $item) {
            if (!is_array($item)) {
                continue;
            }

            $score = ($count - $index) / $count;

            try {
                $results[] = new RedditSearchResult($item, $score);
            } catch (\UnexpectedValueException $exception) {
                continue;
            }
        }

        return $results;
    }

    /**
     * Return rate-limit values reported by the most recent built-in HTTP call.
     *
     * @return array<string, float|int|null>
     */
    public function getLastRateLimit(): array
    {
        return $this->lastRateLimit;
    }

    /**
     * Return the cursor for the next page, when Reddit supplied one.
     */
    public function getAfter(): ?string
    {
        return $this->after;
    }

    /**
     * @param string[] $headers
     */
    private function fetch(string $url, array $headers): string
    {
        if ($this->httpClient !== null) {
            $response = call_user_func($this->httpClient, $url, $headers, $this->timeoutSeconds);

            if (!is_string($response)) {
                throw new \RuntimeException('The Reddit HTTP client must return a response body string.');
            }

            return $response;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => $this->timeoutSeconds,
                'ignore_errors' => true,
                'header' => implode("\r\n", $headers)."\r\n",
            ],
        ]);

        $response = @file_get_contents($url, false, $context);
        $responseHeaders = isset($http_response_header) ? $http_response_header : [];
        $status = $this->readResponseHeaders($responseHeaders);

        if ($response === false || $status === null) {
            throw new \RuntimeException('Unable to fetch Reddit search results.');
        }

        if ($status < 200 || $status >= 300) {
            throw new \RuntimeException('Reddit search failed with HTTP status '.$status.'.');
        }

        return $response;
    }

    /**
     * @param string[] $headers
     *
     * @return int|null
     */
    private function readResponseHeaders(array $headers)
    {
        $status = null;

        foreach ($headers as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d{3})\b/i', $header, $matches)) {
                $status = (int) $matches[1];
                continue;
            }

            if (preg_match('/^x-ratelimit-(used|remaining|reset):\s*(.+)$/i', $header, $matches)) {
                $key = strtolower($matches[1]);
                $value = trim($matches[2]);
                $this->lastRateLimit[$key] = $key === 'reset' ? (int) $value : (float) $value;
            }
        }

        return $status;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function buildUrl(string $query, array $options): string
    {
        $allowedOptions = ['after', 'include_over_18', 'limit', 'sort', 'subreddit', 'time'];

        foreach (array_keys($options) as $option) {
            if (!in_array($option, $allowedOptions, true)) {
                throw new \InvalidArgumentException('Unsupported Reddit search option: '.$option.'.');
            }
        }

        $limit = isset($options['limit']) ? filter_var($options['limit'], FILTER_VALIDATE_INT) : 25;
        if ($limit === false || $limit < 1 || $limit > 100) {
            throw new \InvalidArgumentException('The Reddit search limit must be between 1 and 100.');
        }

        $sort = isset($options['sort']) ? (string) $options['sort'] : 'relevance';
        if (!in_array($sort, ['comments', 'hot', 'new', 'relevance', 'top'], true)) {
            throw new \InvalidArgumentException('Unsupported Reddit search sort: '.$sort.'.');
        }

        $time = isset($options['time']) ? (string) $options['time'] : 'all';
        if (!in_array($time, ['all', 'day', 'hour', 'month', 'week', 'year'], true)) {
            throw new \InvalidArgumentException('Unsupported Reddit search time range: '.$time.'.');
        }

        $params = [
            'q' => $query,
            'limit' => $limit,
            'raw_json' => 1,
            'sort' => $sort,
            't' => $time,
        ];
        $path = '/search';

        if (isset($options['after'])) {
            $after = (string) $options['after'];
            if (!preg_match('/^t3_[A-Za-z0-9]+$/D', $after)) {
                throw new \InvalidArgumentException('The Reddit after option must be a post fullname such as t3_abc123.');
            }
            $params['after'] = $after;
        }

        if (isset($options['include_over_18'])) {
            if (!is_bool($options['include_over_18'])) {
                throw new \InvalidArgumentException('The Reddit include_over_18 option must be boolean.');
            }
            $params['include_over_18'] = $options['include_over_18'] ? 'true' : 'false';
        }

        if (isset($options['subreddit'])) {
            $subreddit = (string) $options['subreddit'];
            if (!preg_match('/^[A-Za-z0-9_]{2,21}$/D', $subreddit)) {
                throw new \InvalidArgumentException('The Reddit subreddit option is invalid.');
            }
            $path = '/r/'.$subreddit.'/search';
            $params['restrict_sr'] = 'true';
        }

        return self::URL.$path.'?'.http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }
}
