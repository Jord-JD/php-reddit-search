<?php

namespace JordJD\RedditSearch;

use JordJD\BaseSearch\SearchResult;

class RedditSearchResult extends SearchResult
{
    /** @var string */
    private $author;

    /** @var string */
    private $permalink;

    /** @var string */
    private $subreddit;

    public function __construct(array $item, float $score)
    {
        if (!isset($item['data']) || !is_array($item['data'])) {
            throw new \UnexpectedValueException('Reddit result data is missing.');
        }

        $data = $item['data'];
        if (!isset($data['title']) || !is_string($data['title']) || trim($data['title']) === '') {
            throw new \UnexpectedValueException('Reddit result title is missing.');
        }

        $this->author = isset($data['author']) && is_string($data['author']) ? $data['author'] : '';
        $this->subreddit = isset($data['subreddit']) && is_string($data['subreddit']) ? $data['subreddit'] : '';
        $this->permalink = isset($data['permalink']) && is_string($data['permalink'])
            ? 'https://www.reddit.com'.$data['permalink']
            : '';

        $title = html_entity_decode($data['title'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $url = isset($data['url']) && is_string($data['url']) && $data['url'] !== ''
            ? $data['url']
            : $this->permalink;

        if ($url === '') {
            throw new \UnexpectedValueException('Reddit result URL is missing.');
        }

        $selfText = isset($data['selftext']) && is_string($data['selftext']) ? trim($data['selftext']) : '';
        $flair = isset($data['link_flair_text']) && is_string($data['link_flair_text']) ? trim($data['link_flair_text']) : '';

        if ($selfText !== '') {
            $description = $this->summarize($selfText);
        } elseif ($flair !== '') {
            $description = $flair;
        } elseif ($this->subreddit !== '' && $this->author !== '') {
            $description = 'Posted to '.$this->subreddit.' by '.$this->author;
        } elseif ($this->subreddit !== '') {
            $description = 'Posted to '.$this->subreddit;
        } elseif ($this->author !== '') {
            $description = 'Posted by '.$this->author;
        } else {
            $description = 'Reddit post';
        }

        parent::__construct(
            $title,
            html_entity_decode($description, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            $url,
            $score
        );
    }

    public function getAuthor(): string
    {
        return $this->author;
    }

    public function getPermalink(): string
    {
        return $this->permalink;
    }

    public function getSubreddit(): string
    {
        return $this->subreddit;
    }

    public function toArray(): array
    {
        return parent::toArray() + [
            'permalink' => $this->permalink,
            'subreddit' => $this->subreddit,
            'author' => $this->author,
        ];
    }

    private function summarize(string $text): string
    {
        $text = preg_replace('/\s+/u', ' ', $text);
        $text = is_string($text) ? trim($text) : '';

        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            return mb_strlen($text, 'UTF-8') > 160 ? mb_substr($text, 0, 160, 'UTF-8').'...' : $text;
        }

        return strlen($text) > 160 ? substr($text, 0, 160).'...' : $text;
    }
}
