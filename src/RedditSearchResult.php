<?php

namespace JordJD\RedditSearch;

use JordJD\BaseSearch\Interfaces\SearchResultInterface;

class RedditSearchResult implements SearchResultInterface
{
    private $title;
    private $url;
    private $description;
    private $score;

    public function __construct(array $item, float $score)
    {
        $this->title = html_entity_decode($item['data']['title'], ENT_QUOTES | ENT_HTML5);
        $this->url = $item['data']['url'];

        if ($item['data']['selftext']) {
            $this->description = html_entity_decode(substr($item['data']['selftext'], 0, 160) . '...', ENT_QUOTES | ENT_HTML5);
        } elseif ($item['data']['link_flair_text']) {
            $this->description = html_entity_decode($item['data']['link_flair_text'], ENT_QUOTES | ENT_HTML5);
        } else {
            $this->description = html_entity_decode('Posted to '.$item['data']['subreddit'].' by '.$item['data']['author'], ENT_QUOTES | ENT_HTML5);
        }

        $this->score = $score;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getScore(): float
    {
        return $this->score;
    }
}