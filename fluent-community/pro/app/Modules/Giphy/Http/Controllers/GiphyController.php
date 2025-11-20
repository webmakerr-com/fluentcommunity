<?php

namespace FluentCommunityPro\App\Modules\Giphy\Http\Controllers;

use FluentCommunity\App\Functions\Utility;
use FluentCommunity\Framework\Http\Controller;
use FluentCommunity\Framework\Http\Request\Request;
use FluentCommunity\Framework\Support\Arr;
use FluentCommunity\Framework\Support\Collection;
use FluentCommunityPro\App\Modules\Giphy\Services\GiphyService;

class GiphyController extends Controller
{
    public function index(Request $request)
    {
        $giphyApi = Arr::get( Utility::getOption('fluent_community_features', []), 'giphy_api_key', '');

        if (!$giphyApi) {
            wp_send_json([
                'message' => __(
                    'Giphy API key is not set or invalid. Please set a valid API key in the giphy module.',
                    'fluent-community-pro'
                )
            ], 400);
        }

        $giphy   = new GiphyService($giphyApi);
        $keyword = sanitize_text_field($request->get('q'));

        if ($keyword) {
            $offset = intval($request->get('offset', 0));
            $giphy->setOffset($offset);
            $gifs = $giphy->searchGifs($keyword);
        } else {
            $gifs = $giphy->getTrendingGifs();
        }

        if (!empty($gifs)) {
            $gifs = Collection::make($gifs)->map(function ($gif) {
                return [
                    'images' => [
                        'preview_gif'      => $gif['images']['preview_gif'],
                        'downsized_medium' => $gif['images']['downsized_medium'],
                    ],
                ];
            })->toArray();
        }

        return [
            'gifs' => $gifs
        ];
    }
}
