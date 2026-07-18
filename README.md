# PHP Reddit Search

Search Reddit posts through Reddit's OAuth API and receive normalized search
result objects.

## Installation

```bash
composer require jord-jd/php-reddit-search
```

## Authentication

Reddit requires API clients to authenticate with OAuth and send a unique,
descriptive user agent. Create an application in
[Reddit's app preferences](https://www.reddit.com/prefs/apps), request a token
with the `read` scope, and follow Reddit's
[OAuth documentation](https://github.com/reddit-archive/reddit/wiki/OAuth2).
Tokens expire, so refresh the token in your application before constructing a
new searcher when necessary.

## Usage

```php
use JordJD\RedditSearch\RedditSearcher;

$searcher = new RedditSearcher(
    $accessToken,
    'php:your-application:v1.0 (by /u/your_reddit_username)'
);

$results = $searcher->search('PHP programming language', [
    'subreddit' => 'PHP',
    'sort' => 'top',
    'time' => 'year',
    'limit' => 25,
    'include_over_18' => false,
]);

foreach ($results as $result) {
    echo $result->getTitle();
    echo $result->getDescription();
    echo $result->getUrl();
    echo $result->getPermalink();
    echo $result->getSubreddit();
    echo $result->getAuthor();
    echo $result->getScore();
    echo json_encode($result);
}
```

Supported options are `after`, `include_over_18`, `limit` (1 to 100), `sort`
(`relevance`, `hot`, `top`, `new`, or `comments`), `subreddit`, and `time`
(`hour`, `day`, `week`, `month`, `year`, or `all`). Invalid options fail before
an HTTP request is made.

The normalized score ranges from `1.0` for the first result down toward zero.
Use `getLastRateLimit()` after a built-in HTTP request to inspect Reddit's
`used`, `remaining`, and `reset` rate-limit values. Use `getAfter()` to obtain
the next-page cursor and pass it back as the `after` option.

## Custom HTTP transport

The optional third constructor argument is a callable, useful for applications
with an existing HTTP stack. It receives the URL, header array, and timeout,
and must return the response body as a string.

```php
$searcher = new RedditSearcher(
    $accessToken,
    $userAgent,
    function (string $url, array $headers, int $timeout): string {
        return $yourHttpClient->get($url, $headers, $timeout);
    },
    10
);
```

Version 2 supports PHP 7.1 through current PHP 8.x releases. It replaces the
unauthenticated public endpoint used by version 1, which no longer complies
with [Reddit's API rules](https://github.com/reddit-archive/reddit/wiki/API).
