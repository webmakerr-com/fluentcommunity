<?php

namespace FluentCommunityPro\App\Modules\Giphy\Services;

class GiphyService
{
    protected $apiKey;
    protected $limit;
    protected $offset;

    public function __construct($apiKey, $limit = 20, $offset = 0)
    {
        $this->apiKey = $apiKey;
        $this->limit  = $limit;
        $this->offset = $offset;
    }

    public function searchGifs($searchKeyword)
    {
        $url = 'https://api.giphy.com/v1/gifs/search';

        return $this->makeRequest($url, $searchKeyword);
    }

    public function getTrendingGifs()
    {
        $url = 'https://api.giphy.com/v1/gifs/trending';

        return $this->makeRequest($url);
    }

    protected function makeRequest($url, $searchKeyword = null)
    {
        $args = [
            'api_key' => $this->apiKey,
            'q'       => $searchKeyword,
            'limit'   => $this->limit,
            'offset'  => $this->offset,
        ];

        $response = wp_safe_remote_get(add_query_arg($args, $url), [
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            throw new \Exception(esc_html__('Error loading GIFs: ', 'fluent-community-pro') . esc_html($response->get_error_message()));
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        return $data['data'];
    }

    public function setOffset($offset)
    {
        $this->offset = $offset;
    }
}
